"""
Memory Store - Core long-term memory persistence layer
Handles storage and retrieval of different memory types
Integrates with local embeddings for semantic retrieval
"""

import json
import sqlite3
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional, Tuple
import hashlib

class MemoryStore:
    """
    Persistent memory storage with semantic retrieval capabilities.
    Stores: Semantic, Episodic, Project, Preference, Conversation Summary memories
    """
    
    def __init__(self, db_path: str = './memory_store.db'):
        """Initialize memory store with SQLite backend"""
        self.db_path = db_path
        self.connection = None
        self._init_db()
    
    def _init_db(self):
        """Initialize database tables"""
        self.connection = sqlite3.connect(self.db_path, check_same_thread=False)
        self.connection.row_factory = sqlite3.Row
        
        cursor = self.connection.cursor()
        
        # Core memory table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS memories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                memory_type TEXT NOT NULL,
                content TEXT NOT NULL,
                embedding TEXT,
                importance REAL DEFAULT 0.5,
                confidence REAL DEFAULT 0.7,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_accessed TIMESTAMP,
                access_count INTEGER DEFAULT 0,
                updated_at TIMESTAMP,
                status TEXT DEFAULT 'active',
                metadata TEXT,
                source_message_hash TEXT,
                UNIQUE(user_id, memory_type, source_message_hash)
            )
        ''')
        
        # Project memory tracking
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS project_memories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                project_name TEXT NOT NULL,
                project_type TEXT DEFAULT 'general',
                status TEXT DEFAULT 'active',
                description TEXT,
                last_known_progress TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_updated TIMESTAMP,
                metadata TEXT,
                UNIQUE(user_id, project_name)
            )
        ''')
        
        # Preference tracking
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                preference_key TEXT NOT NULL,
                preference_value TEXT NOT NULL,
                confidence REAL DEFAULT 0.8,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_confirmed TIMESTAMP,
                UNIQUE(user_id, preference_key)
            )
        ''')
        
        # Conversation summaries
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS conversation_summaries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                summary_text TEXT NOT NULL,
                date_range_start TIMESTAMP,
                date_range_end TIMESTAMP,
                key_topics TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        # Memory access log (for optimization)
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS memory_access_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                memory_id INTEGER NOT NULL,
                accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(memory_id) REFERENCES memories(id)
            )
        ''')
        
        # Create indexes
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_user_id ON memories(user_id)')
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_memory_type ON memories(memory_type)')
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_status ON memories(status)')
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_project_user ON project_memories(user_id)')
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_pref_user ON preferences(user_id)')
        
        self.connection.commit()
    
    def create_memory(self, 
                     user_id: str,
                     memory_type: str,
                     content: str,
                     importance: float = 0.5,
                     confidence: float = 0.7,
                     metadata: Dict = None,
                     source_message: str = None) -> int:
        """
        Create a new memory
        
        Args:
            user_id: User identifier
            memory_type: Type of memory (semantic, episodic, project, preference)
            content: Memory content
            importance: Importance score (0.0-1.0)
            confidence: Confidence score (0.0-1.0)
            metadata: Optional metadata dict
            source_message: Source message (for deduplication)
        
        Returns:
            Memory ID
        """
        cursor = self.connection.cursor()
        
        # Generate source hash for deduplication
        source_hash = None
        if source_message:
            source_hash = hashlib.md5(source_message.encode()).hexdigest()
        
        try:
            cursor.execute('''
                INSERT INTO memories 
                (user_id, memory_type, content, importance, confidence, metadata, source_message_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ''', (
                user_id,
                memory_type,
                content,
                importance,
                confidence,
                json.dumps(metadata) if metadata else None,
                source_hash
            ))
            
            self.connection.commit()
            return cursor.lastrowid
        except sqlite3.IntegrityError:
            # Memory already exists, return existing ID
            cursor.execute('''
                SELECT id FROM memories
                WHERE user_id = ? AND source_message_hash = ?
            ''', (user_id, source_hash))
            result = cursor.fetchone()
            return result['id'] if result else None
    
    def update_memory(self,
                     memory_id: int,
                     content: str = None,
                     importance: float = None,
                     confidence: float = None,
                     status: str = None) -> bool:
        """
        Update an existing memory
        """
        cursor = self.connection.cursor()
        
        updates = []
        params = []
        
        if content is not None:
            updates.append('content = ?')
            params.append(content)
        
        if importance is not None:
            updates.append('importance = ?')
            params.append(importance)
        
        if confidence is not None:
            updates.append('confidence = ?')
            params.append(confidence)
        
        if status is not None:
            updates.append('status = ?')
            params.append(status)
        
        if not updates:
            return False
        
        updates.append('updated_at = CURRENT_TIMESTAMP')
        params.append(memory_id)
        
        query = f'UPDATE memories SET {", ".join(updates)} WHERE id = ?'
        cursor.execute(query, params)
        self.connection.commit()
        
        return True
    
    def get_memory(self, memory_id: int) -> Optional[Dict]:
        """Retrieve a specific memory"""
        cursor = self.connection.cursor()
        cursor.execute('SELECT * FROM memories WHERE id = ?', (memory_id,))
        row = cursor.fetchone()
        
        if row:
            self._log_access(memory_id)
            return dict(row)
        
        return None
    
    def get_user_memories(self, user_id: str, memory_type: str = None, 
                         status: str = 'active') -> List[Dict]:
        """Get all memories for a user, optionally filtered by type"""
        cursor = self.connection.cursor()
        
        query = 'SELECT * FROM memories WHERE user_id = ? AND status = ?'
        params = [user_id, status]
        
        if memory_type:
            query += ' AND memory_type = ?'
            params.append(memory_type)
        
        query += ' ORDER BY importance DESC, last_accessed DESC'
        
        cursor.execute(query, params)
        return [dict(row) for row in cursor.fetchall()]
    
    def merge_memories(self, primary_id: int, duplicate_id: int) -> bool:
        """
        Merge duplicate memories into one
        Updates access count and importance of primary, deactivates duplicate
        """
        primary = self.get_memory(primary_id)
        duplicate = self.get_memory(duplicate_id)
        
        if not primary or not duplicate:
            return False
        
        # Merge importance and confidence
        new_importance = max(primary['importance'], duplicate['importance'])
        new_confidence = (primary['confidence'] + duplicate['confidence']) / 2.0
        
        # Merge access count
        new_access_count = primary['access_count'] + duplicate['access_count']
        
        cursor = self.connection.cursor()
        
        # Update primary memory
        cursor.execute('''
            UPDATE memories 
            SET importance = ?, confidence = ?, access_count = ?
            WHERE id = ?
        ''', (new_importance, new_confidence, new_access_count, primary_id))
        
        # Deactivate duplicate
        cursor.execute('''
            UPDATE memories
            SET status = 'merged'
            WHERE id = ?
        ''', (duplicate_id,))
        
        self.connection.commit()
        return True
    
    def create_project_memory(self,
                            user_id: str,
                            project_name: str,
                            description: str = None,
                            status: str = 'active',
                            project_type: str = 'general') -> int:
        """Create or update a project memory"""
        cursor = self.connection.cursor()
        
        try:
            cursor.execute('''
                INSERT INTO project_memories
                (user_id, project_name, description, status, project_type)
                VALUES (?, ?, ?, ?, ?)
            ''', (user_id, project_name, description, status, project_type))
            
            self.connection.commit()
            return cursor.lastrowid
        except sqlite3.IntegrityError:
            # Project exists, update it
            cursor.execute('''
                UPDATE project_memories
                SET description = ?, status = ?, last_updated = CURRENT_TIMESTAMP
                WHERE user_id = ? AND project_name = ?
            ''', (description, status, user_id, project_name))
            
            self.connection.commit()
            
            cursor.execute('''
                SELECT id FROM project_memories
                WHERE user_id = ? AND project_name = ?
            ''', (user_id, project_name))
            
            result = cursor.fetchone()
            return result['id'] if result else None
    
    def get_active_projects(self, user_id: str) -> List[Dict]:
        """Get all active projects for a user"""
        cursor = self.connection.cursor()
        cursor.execute('''
            SELECT * FROM project_memories
            WHERE user_id = ? AND status = 'active'
            ORDER BY last_updated DESC
        ''', (user_id,))
        
        return [dict(row) for row in cursor.fetchall()]
    
    def update_project_status(self, project_id: int, status: str, progress: str = None) -> bool:
        """Update project status and progress"""
        cursor = self.connection.cursor()
        
        cursor.execute('''
            UPDATE project_memories
            SET status = ?, last_known_progress = ?, last_updated = CURRENT_TIMESTAMP
            WHERE id = ?
        ''', (status, progress, project_id))
        
        self.connection.commit()
        return True
    
    def set_preference(self, user_id: str, key: str, value: str, confidence: float = 0.8) -> bool:
        """Set or update a user preference"""
        cursor = self.connection.cursor()
        
        try:
            cursor.execute('''
                INSERT INTO preferences (user_id, preference_key, preference_value, confidence)
                VALUES (?, ?, ?, ?)
            ''', (user_id, key, value, confidence))
            
            self.connection.commit()
            return True
        except sqlite3.IntegrityError:
            cursor.execute('''
                UPDATE preferences
                SET preference_value = ?, confidence = ?, last_confirmed = CURRENT_TIMESTAMP
                WHERE user_id = ? AND preference_key = ?
            ''', (value, confidence, user_id, key))
            
            self.connection.commit()
            return True
    
    def get_preferences(self, user_id: str, min_confidence: float = 0.5) -> Dict[str, str]:
        """Get user preferences"""
        cursor = self.connection.cursor()
        cursor.execute('''
            SELECT preference_key, preference_value FROM preferences
            WHERE user_id = ? AND confidence >= ?
            ORDER BY confidence DESC
        ''', (user_id, min_confidence))
        
        return {row['preference_key']: row['preference_value'] for row in cursor.fetchall()}
    
    def save_conversation_summary(self,
                                 user_id: str,
                                 summary_text: str,
                                 key_topics: List[str],
                                 date_range_start: datetime = None,
                                 date_range_end: datetime = None) -> int:
        """Save a conversation summary"""
        cursor = self.connection.cursor()
        
        cursor.execute('''
            INSERT INTO conversation_summaries
            (user_id, summary_text, date_range_start, date_range_end, key_topics)
            VALUES (?, ?, ?, ?, ?)
        ''', (
            user_id,
            summary_text,
            date_range_start,
            date_range_end,
            json.dumps(key_topics)
        ))
        
        self.connection.commit()
        return cursor.lastrowid
    
    def _log_access(self, memory_id: int):
        """Log memory access for scoring purposes"""
        cursor = self.connection.cursor()
        
        cursor.execute('''
            INSERT INTO memory_access_log (memory_id)
            VALUES (?)
        ''', (memory_id,))
        
        cursor.execute('''
            UPDATE memories
            SET last_accessed = CURRENT_TIMESTAMP, access_count = access_count + 1
            WHERE id = ?
        ''', (memory_id,))
        
        self.connection.commit()
    
    def deactivate_memory(self, memory_id: int, reason: str = None) -> bool:
        """Deactivate a memory (soft delete)"""
        cursor = self.connection.cursor()
        
        cursor.execute('''
            UPDATE memories
            SET status = 'inactive'
            WHERE id = ?
        ''', (memory_id,))
        
        self.connection.commit()
        return True
    
    def cleanup_old_memories(self, days: int = 90) -> int:
        """
        Archive old episodic memories to conversation summaries
        Keep important semantic and project memories regardless of age
        """
        cursor = self.connection.cursor()
        cutoff_date = datetime.now() - timedelta(days=days)
        
        # Find episodic memories to clean
        cursor.execute('''
            SELECT * FROM memories
            WHERE memory_type = 'episodic'
            AND created_at < ?
            AND status = 'active'
            AND importance < 0.7
            ORDER BY importance ASC
            LIMIT 100
        ''', (cutoff_date,))
        
        old_memories = [dict(row) for row in cursor.fetchall()]
        cleaned = 0
        
        for memory in old_memories:
            # Deactivate but don't delete
            self.deactivate_memory(memory['id'])
            cleaned += 1
        
        return cleaned
    
    def close(self):
        """Close database connection"""
        if self.connection:
            self.connection.close()
    
    def __del__(self):
        """Cleanup on destruction"""
        self.close()
