"""Dedicated local HMAC authentication and replay protection for inference bridge."""
import hashlib,hmac,os,re,sqlite3,time
from pathlib import Path
from flask import request
_ID=re.compile(r'^[A-Za-z0-9][A-Za-z0-9_.:-]{7,79}$')
def _db():
 p=Path(__file__).with_name('runtime').joinpath('inference-bridge-replay.db');p.parent.mkdir(parents=True,exist_ok=True);c=sqlite3.connect(str(p),timeout=2,isolation_level=None);c.execute('PRAGMA busy_timeout=2000');c.execute('CREATE TABLE IF NOT EXISTS bridge_replays (request_id TEXT PRIMARY KEY, expires_at REAL NOT NULL)');return c
def verify_crisis_bridge(expected_path='/internal/crisis-assessment'):
 if request.remote_addr not in {'127.0.0.1','::1'}: return False,'loopback_required',403
 secret=os.getenv('MINDCARE_INFERENCE_BRIDGE_SECRET','')
 rid=request.headers.get('X-MindCare-Inference-Request-ID','');ts=request.headers.get('X-MindCare-Inference-Timestamp','');sig=request.headers.get('X-MindCare-Inference-Signature','')
 if len(secret)<32 or not _ID.fullmatch(rid) or not ts.isdigit() or not re.fullmatch(r'[0-9a-f]{64}',sig): return False,'invalid_authentication',401
 if abs(time.time()-int(ts))>60:return False,'expired_authentication',401
 body=request.get_data(cache=True); canonical=f'v1\nPOST\n{expected_path}\n{rid}\n{ts}\n{hashlib.sha256(body).hexdigest()}'
 if not hmac.compare_digest(hmac.new(secret.encode(),canonical.encode(),hashlib.sha256).hexdigest(),sig): return False,'invalid_authentication',401
 try:
  with _db() as c:
   c.execute('BEGIN IMMEDIATE');c.execute('DELETE FROM bridge_replays WHERE expires_at<?',(time.time(),))
   try:c.execute('INSERT INTO bridge_replays(request_id,expires_at) VALUES (?,?)',(rid,time.time()+120))
   except sqlite3.IntegrityError:c.execute('ROLLBACK');return False,'replayed_request',409
   c.execute('COMMIT')
 except sqlite3.Error:return False,'authentication_store_unavailable',503
 return True,rid,200
