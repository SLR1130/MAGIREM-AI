<?php

namespace App\Services;

use PDO;
use RuntimeException;

/** Synthetic-only Phase 4B consent authority. Not institutional policy storage. */
final class MemoryConsentDecision
{
    public function __construct(
        public readonly string $subject,
        public readonly string $purpose,
        public readonly string $policyVersion,
        public readonly string $state,
        public readonly int $revision,
        public readonly string $updatedAt,
    ) {}

    public function allowsMemory(): bool
    {
        return $this->purpose === 'long_term_memory' && $this->state === 'granted';
    }
}

final class MemoryConsentService
{
    public const PURPOSE = 'long_term_memory';
    public const STATES = ['not_granted', 'granted', 'revoked', 'stale'];

    private ?PDO $pdo = null;

    public function __construct(?string $databasePath = null, ?string $policyVersion = null)
    {
        $this->databasePath = $databasePath ?: (string) config('services.mindcare_ml.phase4b.consent_db', __DIR__ . '/../../../storage/app/phase4b-consent.db');
        $this->policyVersion = $policyVersion ?: (string) config('services.mindcare_ml.phase4b.policy_version', 'synthetic-policy-v1');
        $this->initialize();
    }

    private string $databasePath;
    private string $policyVersion;

    private function connection(): PDO
    {
        if ($this->pdo === null) {
            $directory = dirname($this->databasePath);
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new RuntimeException('Consent database directory unavailable.');
            }
            $this->pdo = new PDO('sqlite:' . $this->databasePath, null, null, [PDO::ATTR_TIMEOUT => 10]);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA busy_timeout=10000');
        }
        return $this->pdo;
    }

    private function initialize(): void
    {
        $db = $this->connection();
        $db->exec('CREATE TABLE IF NOT EXISTS memory_consent (subject TEXT PRIMARY KEY, purpose TEXT NOT NULL, policy_version TEXT NOT NULL, state TEXT NOT NULL, revision INTEGER NOT NULL, granted_at TEXT, revoked_at TEXT, updated_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE IF NOT EXISTS consent_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, subject TEXT NOT NULL, event TEXT NOT NULL, state TEXT NOT NULL, policy_version TEXT NOT NULL, revision INTEGER NOT NULL, occurred_at TEXT NOT NULL)');
    }

    public function decisionForAuthenticatedSubject($subject): MemoryConsentDecision
    {
        $current = auth()->id();
        if ($current === null || (string) $current !== (string) $subject) {
            throw new RuntimeException('Authenticated subject mismatch.');
        }
        return $this->decision($subject);
    }

    public function decision($subject): MemoryConsentDecision
    {
        $stmt = $this->connection()->prepare('SELECT subject,purpose,policy_version,state,revision,updated_at FROM memory_consent WHERE subject = ?');
        $stmt->execute([(string) $subject]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return new MemoryConsentDecision((string) $subject, self::PURPOSE, $this->policyVersion, 'not_granted', 0, '');
        }
        return new MemoryConsentDecision($row['subject'], $row['purpose'], $row['policy_version'], $row['state'], (int) $row['revision'], $row['updated_at']);
    }

    public function setStateForAuthenticatedSubject(string $state, ?string $policyVersion = null): MemoryConsentDecision
    {
        $subject = auth()->id();
        if ($subject === null) {
            throw new RuntimeException('Authenticated subject required.');
        }
        return $this->setState($subject, $state, $policyVersion);
    }

    public function setState($subject, string $state, ?string $policyVersion = null): MemoryConsentDecision
    {
        if (!in_array($state, self::STATES, true)) {
            throw new RuntimeException('Invalid consent state.');
        }
        $version = $policyVersion ?: $this->policyVersion;
        $now = gmdate('c');
        $db = $this->connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT revision FROM memory_consent WHERE subject = ?');
            $stmt->execute([(string) $subject]);
            $previous = $stmt->fetchColumn();
            $revision = $previous === false ? 1 : ((int) $previous + 1);
            $insert = $db->prepare('INSERT INTO memory_consent(subject,purpose,policy_version,state,revision,granted_at,revoked_at,updated_at) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(subject) DO UPDATE SET purpose=excluded.purpose,policy_version=excluded.policy_version,state=excluded.state,revision=excluded.revision,granted_at=excluded.granted_at,revoked_at=excluded.revoked_at,updated_at=excluded.updated_at');
            $insert->execute([(string) $subject, self::PURPOSE, $version, $state, $revision, $state === 'granted' ? $now : null, $state === 'revoked' ? $now : null, $now]);
            $audit = $db->prepare('INSERT INTO consent_audit(subject,event,state,policy_version,revision,occurred_at) VALUES(?,?,?,?,?,?)');
            $audit->execute([(string) $subject, 'consent_state_changed', $state, $version, $revision, $now]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return $this->decision($subject);
    }
}

