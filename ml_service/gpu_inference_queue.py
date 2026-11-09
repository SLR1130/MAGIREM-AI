"""Cross-process, fail-closed admission for local Ollama inference.

The ledger stores only hashed request IDs and ownership metadata.  It never
stores prompts, responses, user IDs, or consent data.  A live owner's lease is
renewed; an expired lease is reclaimed only after the owner process is gone.
"""
from __future__ import annotations
from contextlib import contextmanager
from dataclasses import dataclass
import hashlib, os, sqlite3, threading, time, uuid
from pathlib import Path
from typing import Iterator, Callable, Any

def _env_int(name, default, minimum, maximum):
    try: return max(minimum, min(maximum, int(os.getenv(name, str(default)))))
    except (TypeError, ValueError): return default

def _env_float(name, default, minimum, maximum):
    try: return max(minimum, min(maximum, float(os.getenv(name, str(default)))))
    except (TypeError, ValueError): return default

class InferenceQueueBusy(RuntimeError):
    code = 'GPU_QUEUE_BUSY'
class InferenceAlreadyQueued(RuntimeError):
    code = 'GPU_REQUEST_IN_PROGRESS'
class InferenceQueueUnavailable(RuntimeError):
    code = 'GPU_QUEUE_UNAVAILABLE'

@dataclass(frozen=True)
class QueueAdmission:
    wait_ms: int
    queue_position: int

class GpuInferenceQueue:
    def __init__(self):
        self.capacity = _env_int('MINDCARE_GPU_MAX_CONCURRENT', 1, 1, 1)
        self.queue_limit = _env_int('MINDCARE_GPU_QUEUE_LIMIT', 2, 0, 20)
        self.wait_seconds = _env_float('MINDCARE_GPU_QUEUE_WAIT_SECONDS', 20.0, 1.0, 25.0)
        self.lease_seconds = _env_float('MINDCARE_GPU_LEASE_SECONDS', 170.0, 30.0, 175.0)
        self.poll_seconds = _env_float('MINDCARE_GPU_QUEUE_POLL_SECONDS', .20, .05, 1.0)
        configured = os.getenv('MINDCARE_GPU_QUEUE_DB', '').strip()
        self.database = Path(configured) if configured else Path(__file__).with_name('runtime').joinpath('gpu-inference-queue.db')
        self._schema_lock = threading.Lock(); self._initialized = False

    def _connect(self):
        self.database.parent.mkdir(parents=True, exist_ok=True)
        c = sqlite3.connect(str(self.database), timeout=.5, isolation_level=None)
        c.execute('PRAGMA busy_timeout=500'); c.execute('PRAGMA journal_mode=WAL')
        return c

    def _ensure_schema(self):
        if self._initialized: return
        with self._schema_lock:
            if self._initialized: return
            with self._connect() as c:
                c.execute('''CREATE TABLE IF NOT EXISTS gpu_inference_queue (
                    request_key TEXT PRIMARY KEY, status TEXT NOT NULL CHECK(status IN ('waiting','active')),
                    created_at REAL NOT NULL, lease_until REAL, priority INTEGER NOT NULL DEFAULT 0,
                    owner_token TEXT, owner_pid INTEGER, heartbeat_at REAL, waiting_until REAL)''')
                cols={row[1] for row in c.execute('PRAGMA table_info(gpu_inference_queue)')}
                for name, definition in [('priority','INTEGER NOT NULL DEFAULT 0'),('owner_token','TEXT'),('owner_pid','INTEGER'),('heartbeat_at','REAL'),('waiting_until','REAL')]:
                    if name not in cols: c.execute(f'ALTER TABLE gpu_inference_queue ADD COLUMN {name} {definition}')
                c.execute('CREATE INDEX IF NOT EXISTS gpu_inference_queue_order ON gpu_inference_queue(status, priority, created_at)')
            self._initialized = True

    @staticmethod
    def _key(value): return hashlib.sha256(str(value).encode('utf-8','replace')).hexdigest()

    @staticmethod
    def _pid_alive(pid):
        if not pid: return False
        try:
            os.kill(int(pid), 0); return True
        except (OSError, ValueError, ProcessLookupError): return False

    def _tx(self, callback: Callable[[sqlite3.Connection], Any]):
        self._ensure_schema(); last = None
        for attempt in range(4):
            c = None
            try:
                c=self._connect(); c.execute('BEGIN IMMEDIATE'); result=callback(c); c.execute('COMMIT'); return result
            except sqlite3.OperationalError as exc:
                last=exc
                if c:
                    try:c.execute('ROLLBACK')
                    except sqlite3.Error: pass
                if 'locked' not in str(exc).lower() and 'busy' not in str(exc).lower(): raise
                time.sleep(.05*(attempt+1))
            finally:
                if c: c.close()
        raise InferenceQueueUnavailable('GPU queue storage is temporarily unavailable.') from last

    def _prune(self, c, now):
        # Waiting reservations are safe to remove after their bounded deadline.
        c.execute("DELETE FROM gpu_inference_queue WHERE status='waiting' AND waiting_until < ?", (now,))
        # Never infer that an expired lease means Ollama stopped. Reclaim only a
        # lease whose owner process is demonstrably gone; otherwise fail closed.
        for key, pid in c.execute("SELECT request_key, owner_pid FROM gpu_inference_queue WHERE status='active' AND lease_until < ?", (now,)).fetchall():
            if not self._pid_alive(pid): c.execute('DELETE FROM gpu_inference_queue WHERE request_key=?', (key,))

    def status(self):
        def read(c):
            now=time.time(); self._prune(c,now)
            active=c.execute("SELECT COUNT(*) FROM gpu_inference_queue WHERE status='active'").fetchone()[0]
            waiting=c.execute("SELECT COUNT(*) FROM gpu_inference_queue WHERE status='waiting'").fetchone()[0]
            blocked=c.execute("SELECT COUNT(*) FROM gpu_inference_queue WHERE status='active' AND lease_until < ?",(now,)).fetchone()[0]
            return {'active':active,'waiting':waiting,'capacity':self.capacity,'queue_limit':self.queue_limit,'blocked_expired':blocked}
        return self._tx(read)

    def _remove(self, key, token):
        # The request key is unique. Remove our active fenced lease or our
        # still-waiting reservation so cancellation cannot leave a ghost slot.
        self._tx(lambda c: c.execute("DELETE FROM gpu_inference_queue WHERE request_key=? AND (owner_token=? OR (status='waiting' AND owner_token IS NULL))",(key,token)))

    def _renew(self, key, token, stop):
        interval=max(5.0,min(30.0,self.lease_seconds/3))
        while not stop.wait(interval):
            try:
                now=time.time(); count=self._tx(lambda c:c.execute("UPDATE gpu_inference_queue SET lease_until=?, heartbeat_at=? WHERE request_key=? AND owner_token=? AND status='active'",(now+self.lease_seconds,now,key,token)).rowcount)
                if count != 1: return
            except InferenceQueueUnavailable:
                # Keep the lease alive only when storage is available; the
                # active owner remains fenced by PID if renewal is interrupted.
                continue

    @contextmanager
    def acquire(self, request_id: str, priority: str='normal') -> Iterator[QueueAdmission]:
        self._ensure_schema(); key=self._key(request_id); started=time.monotonic(); token=uuid.uuid4().hex; pid=os.getpid(); value=10 if priority=='crisis' else 0
        def reserve(c):
            now=time.time(); self._prune(c,now)
            if c.execute('SELECT 1 FROM gpu_inference_queue WHERE request_key=?',(key,)).fetchone(): raise InferenceAlreadyQueued('This request is already queued.')
            if c.execute('SELECT COUNT(*) FROM gpu_inference_queue').fetchone()[0] >= self.capacity+self.queue_limit: raise InferenceQueueBusy('The local AI queue is full.')
            c.execute("INSERT INTO gpu_inference_queue(request_key,status,created_at,lease_until,priority,owner_token,owner_pid,heartbeat_at,waiting_until) VALUES (?,'waiting',?,NULL,?,NULL,NULL,NULL,?)",(key,now,value,now+self.wait_seconds))
        self._tx(reserve); active=False; stop=threading.Event(); renewer=None
        try:
            position=0
            while time.monotonic()-started < self.wait_seconds:
                def admit(c):
                    nonlocal position
                    now=time.time(); self._prune(c,now)
                    if not c.execute('SELECT 1 FROM gpu_inference_queue WHERE request_key=?',(key,)).fetchone(): raise InferenceQueueBusy('The queue reservation expired.')
                    rows=c.execute("SELECT request_key FROM gpu_inference_queue WHERE status='waiting' ORDER BY (priority + MIN(10, CAST((? - created_at)/2 AS INTEGER))) DESC, created_at ASC",(now,)).fetchall(); keys=[r[0] for r in rows]; position=(keys.index(key)+1) if key in keys else 0
                    active_count=c.execute("SELECT COUNT(*) FROM gpu_inference_queue WHERE status='active'").fetchone()[0]
                    if active_count < self.capacity and position == 1:
                        now=time.time(); c.execute("UPDATE gpu_inference_queue SET status='active',lease_until=?,owner_token=?,owner_pid=?,heartbeat_at=?,waiting_until=NULL WHERE request_key=?",(now+self.lease_seconds,token,pid,now,key)); return True
                    return False
                if self._tx(admit): active=True; break
                time.sleep(self.poll_seconds)
            if not active: raise InferenceQueueBusy('The local AI queue did not become available within the bounded wait.')
            renewer=threading.Thread(target=self._renew,args=(key,token,stop),daemon=True);renewer.start()
            yield QueueAdmission(round((time.monotonic()-started)*1000),position)
        finally:
            stop.set()
            if renewer: renewer.join(timeout=2)
            try:self._remove(key,token)
            except InferenceQueueUnavailable: pass

queue=GpuInferenceQueue()
