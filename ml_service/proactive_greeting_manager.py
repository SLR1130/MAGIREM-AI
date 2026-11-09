"""
Proactive Greeting Manager - Initiates conversation naturally based on long-term memory
Decides whether to check in about recent topics without forcing it
Respects memory dormancy and prevents repetitive check-ins
Uses LLM to generate natural greetings, not templates
"""

from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
import math
import json

class ProactiveGreetingManager:
    """
    Manages proactive conversation initiation based on:
    - Recent unresolved topics from memory
    - Time elapsed since last update
    - Emotional importance
    - Whether user has already responded to check-in
    - Memory dormancy state
    """
    
    def __init__(self, memory_store, memory_retriever):
        """
        Initialize proactive greeting manager
        
        Args:
            memory_store: MemoryStore instance for accessing memories
            memory_retriever: MemoryRetriever instance for ranking
        """
        self.memory_store = memory_store
        self.memory_retriever = memory_retriever
        
        # Track check-in history per user (in-memory for now)
        self.recent_checkins = {}  # user_id -> [(topic, timestamp, response_status)]
    
    def should_proactively_greet(self,
                                user_id: str,
                                conversation_history: List[Dict] = None) -> Dict:
        """
        Determine if we should initiate a check-in about a recent topic
        
        Decision factors:
        - Is there a meaningful recent topic?
        - Has enough time passed since last update?
        - Has user already responded to check-in about this topic?
        - Is the topic still unresolved?
        - Is it emotionally important?
        - Would it feel natural to bring up?
        
        Args:
            user_id: User identifier
            conversation_history: Recent conversation (to check context relevance)
        
        Returns:
            {
                'should_greet': bool,
                'topic_memory': memory object (if should greet),
                'confidence': float (0.0-1.0),
                'reasoning': str,
                'greeting_style': str,  # 'project_checkin', 'emotional_support', 'normal_greeting'
                'suppress_reason': str  # If should_greet=False, why we're not greeting
            }
        """
        
        # Get all active memories
        all_memories = self.memory_store.get_user_memories(user_id, status='active')
        
        if not all_memories:
            return {
                'should_greet': False,
                'topic_memory': None,
                'confidence': 0.0,
                'reasoning': 'No memories available',
                'greeting_style': 'generic',
                'suppress_reason': 'no_memories'
            }
        
        # Get candidates for proactive check-in
        candidates = self._get_checkin_candidates(user_id, all_memories, conversation_history)
        
        if not candidates:
            return {
                'should_greet': False,
                'topic_memory': None,
                'confidence': 0.0,
                'reasoning': 'No suitable candidates for check-in',
                'greeting_style': 'generic',
                'suppress_reason': 'no_suitable_candidates'
            }
        
        # Select the best candidate
        best_candidate = candidates[0]  # Already sorted by rank
        
        # Check if we've already checked in about this recently
        if self._has_recent_checkin(user_id, best_candidate['id']):
            return {
                'should_greet': False,
                'topic_memory': best_candidate,
                'confidence': best_candidate['check_in_score'],
                'reasoning': 'Already checked in recently about this topic',
                'greeting_style': 'none',
                'suppress_reason': 'recent_checkin_exists'
            }
        
        # Check if topic is too old/dormant
        dormancy_state = self._get_dormancy_state(best_candidate)
        
        if dormancy_state == 'dormant':
            return {
                'should_greet': False,
                'topic_memory': best_candidate,
                'confidence': best_candidate['check_in_score'],
                'reasoning': 'Topic is dormant - don\'t proactively surface',
                'greeting_style': 'none',
                'suppress_reason': 'topic_dormant'
            }
        
        # Check if current conversation already mentions this topic
        if self._is_current_conversation_relevant(best_candidate, conversation_history):
            return {
                'should_greet': False,
                'topic_memory': best_candidate,
                'confidence': best_candidate['check_in_score'],
                'reasoning': 'Topic already in current conversation',
                'greeting_style': 'none',
                'suppress_reason': 'topic_in_current_conversation'
            }
        
        # Decision: should we greet?
        # Use conservative threshold - only if score is high enough
        MIN_THRESHOLD = 0.6
        
        if best_candidate['check_in_score'] < MIN_THRESHOLD:
            return {
                'should_greet': False,
                'topic_memory': best_candidate,
                'confidence': best_candidate['check_in_score'],
                'reasoning': f'Check-in score {best_candidate["check_in_score"]:.0%} below threshold {MIN_THRESHOLD:.0%}',
                'greeting_style': 'none',
                'suppress_reason': 'low_score'
            }
        
        # YES - we should greet
        return {
            'should_greet': True,
            'topic_memory': best_candidate,
            'confidence': best_candidate['check_in_score'],
            'reasoning': best_candidate['reasoning'],
            'greeting_style': self._determine_greeting_style(best_candidate),
            'suppress_reason': None
        }
    
    def _get_checkin_candidates(self,
                               user_id: str,
                               all_memories: List[Dict],
                               conversation_history: List[Dict] = None) -> List[Dict]:
        """
        Get memories suitable for proactive check-in
        Scored and ranked conservatively
        """
        candidates = []
        
        for memory in all_memories:
            # Skip certain memory types
            if memory['memory_type'] not in ['project', 'semantic', 'episodic']:
                continue
            
            # Skip very new memories (give user time to expand on them)
            age_days = (datetime.now() - self._parse_timestamp(memory['created_at'])).days
            if age_days < 1:
                continue
            
            # Skip very old memories unless they're important
            if age_days > 30 and memory['importance'] < 0.7:
                continue
            
            # Skip LOW importance memories (they're not worth checking in about)
            if memory['importance'] < 0.6:
                continue
            
            # Calculate check-in score
            score = self._calculate_checkin_score(
                memory,
                age_days,
                conversation_history
            )
            
            # Only include if score is meaningful (lowered threshold slightly)
            if score > 0.25:
                candidates.append({
                    **memory,
                    'check_in_score': score,
                    'age_days': age_days,
                    'reasoning': self._explain_score(memory, score, age_days)
                })
        
        # Sort by score (descending)
        candidates.sort(key=lambda x: x['check_in_score'], reverse=True)
        
        return candidates
    
    def _calculate_checkin_score(self,
                                memory: Dict,
                                age_days: int,
                                conversation_history: List[Dict] = None) -> float:
        """
        Calculate score for how suitable this memory is for proactive check-in
        
        Signals:
        - Recency (sweet spot: 1-7 days old)
        - Importance (high importance = more relevant)
        - Type (projects > emotional > generic)
        - Previous conversation resolution status
        - Access frequency (frequently accessed memories = user cares)
        """
        score = 0.0
        
        # 1. RECENCY factor (0.0-1.0)
        # Sweet spot: 1-7 days old (0.8+ score)
        # Too new (< 1 day): 0.5 - give user time to expand
        # 7-14 days: 0.6-0.8
        # 15-30 days: 0.3-0.6
        # 30+ days: 0.1-0.3 (unless important)
        
        if age_days <= 1:
            recency = 0.5  # Too new, let user start
        elif age_days <= 7:
            # Sweet spot: full score
            recency = 0.9 - (age_days - 1) * 0.05  # Decay from 0.9 to 0.75
        elif age_days <= 14:
            recency = 0.75 - (age_days - 7) * 0.05  # Decay to 0.4
        elif age_days <= 30:
            recency = 0.4 - (age_days - 14) * 0.015  # Slow decay
        else:
            recency = max(0.1, 0.25 - (age_days - 30) * 0.001)  # Very slow decay for old memories
        
        # 2. IMPORTANCE factor (0.0-1.0)
        # High importance = more likely to check in
        importance = float(memory.get('importance', 0.5))
        
        # 3. MEMORY TYPE factor
        # Projects are highest priority for check-ins
        mem_type = memory['memory_type']
        if mem_type == 'project':
            type_factor = 1.0
        elif mem_type == 'episodic' and memory.get('importance', 0) > 0.7:
            type_factor = 0.8  # Important episodic events
        else:
            type_factor = 0.5
        
        # 4. ACCESS FREQUENCY
        # If user has accessed this memory recently, they care about it
        access_count = memory.get('access_count', 0)
        if access_count == 0:
            freq_factor = 0.6
        elif access_count <= 3:
            freq_factor = 0.75
        else:
            freq_factor = 0.9
        
        # 5. CONFIDENCE in the memory
        # Don't check in about low-confidence memories
        confidence = float(memory.get('confidence', 0.7))
        if confidence < 0.6:
            return 0.2  # Too uncertain, skip
        
        # Combine factors
        score = (
            recency * 0.45 +        # Recency is primary (when was it discussed?)
            importance * 0.25 +     # Importance (how much did they care?)
            type_factor * 0.15 +    # Type matters (projects > others)
            freq_factor * 0.10 +    # Frequency shows they care
            confidence * 0.05       # Confidence prevents false check-ins
        )
        
        return min(1.0, max(0.0, score))
    
    def _explain_score(self, memory: Dict, score: float, age_days: int) -> str:
        """Generate explanation for why this memory scored as it did"""
        parts = []
        
        if age_days <= 7:
            parts.append(f"Recent topic ({age_days}d old)")
        elif age_days <= 14:
            parts.append(f"Moderately recent ({age_days}d old)")
        else:
            parts.append(f"Older topic ({age_days}d old)")
        
        if memory['importance'] > 0.8:
            parts.append("highly important")
        
        if memory['memory_type'] == 'project':
            parts.append("is a project")
        
        if memory['access_count'] > 2:
            parts.append("frequently accessed")
        
        return "; ".join(parts)
    
    def _has_recent_checkin(self, user_id: str, memory_id: int) -> bool:
        """
        Check if we've already checked in about this memory recently
        Prevents repetitive check-ins
        """
        if user_id not in self.recent_checkins:
            return False
        
        checkins = self.recent_checkins[user_id]
        
        # Look for check-in about this memory in last 2 days
        cutoff = datetime.now() - timedelta(days=2)
        
        for checkin_memory_id, checkin_time, response_status in checkins:
            if checkin_memory_id == memory_id and checkin_time > cutoff:
                # If user already responded, don't check in again
                if response_status == 'responded':
                    return True
        
        return False
    
    def _get_dormancy_state(self, memory: Dict) -> str:
        """
        Determine if memory is ACTIVE, ACCESSIBLE, or DORMANT
        
        ACTIVE: Recent (1-7 days) + important → proactively surface
        ACCESSIBLE: Older (8-30 days) or lower importance → don't proactively surface but highly retrievable
        DORMANT: Very old (30+ days) → don't proactively surface, but retrievable if user references it
        """
        created_at = memory.get('created_at')
        last_accessed = memory.get('last_accessed')
        
        try:
            if isinstance(created_at, str):
                created = datetime.fromisoformat(created_at)
            else:
                created = created_at
        except:
            return 'accessible'
        
        age_days = (datetime.now() - created).days
        importance = float(memory.get('importance', 0.5))
        
        # Active: very recent and important, OR recently accessed
        if age_days <= 3 and importance > 0.7:
            return 'active'
        
        # Check recent access
        if last_accessed:
            try:
                if isinstance(last_accessed, str):
                    last_access = datetime.fromisoformat(last_accessed)
                else:
                    last_access = last_accessed
                
                days_since_access = (datetime.now() - last_access).days
                
                # Recently accessed = still active
                if days_since_access <= 3:
                    return 'active'
            except:
                pass
        
        # Accessible: older but still retrievable
        if age_days <= 30:
            return 'accessible'
        
        # Dormant: very old
        return 'dormant'
    
    def _is_current_conversation_relevant(self,
                                         memory: Dict,
                                         conversation_history: List[Dict] = None) -> bool:
        """
        Check if the topic is already being discussed in current conversation
        Don't redundantly bring it up if user is already talking about it
        """
        if not conversation_history or len(conversation_history) == 0:
            return False
        
        # Look at recent messages (last 2-3 messages)
        recent_messages = [m.get('user', '').lower() for m in conversation_history[-3:]]
        memory_content = memory['content'].lower()
        
        # Check for keyword overlap
        memory_keywords = self._extract_keywords(memory_content)
        
        for msg in recent_messages:
            msg_keywords = self._extract_keywords(msg)
            
            # If significant overlap, topic is already being discussed
            overlap = len(set(memory_keywords) & set(msg_keywords))
            if overlap >= 2:
                return True
        
        return False
    
    def _extract_keywords(self, text: str, num_keywords: int = 5) -> List[str]:
        """Extract important keywords from text"""
        import re
        
        # Remove common words
        stopwords = {'the', 'a', 'an', 'and', 'or', 'but', 'is', 'was', 'are', 'were', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'with', 'to', 'for', 'of', 'in', 'on', 'at', 'by'}
        
        # Extract words and capitalized terms
        words = re.findall(r'\b[A-Z][a-z]+\b|\b[a-z]{4,}\b', text)
        keywords = [w.lower() for w in words if w.lower() not in stopwords]
        
        return keywords[:num_keywords]
    
    def _determine_greeting_style(self, memory: Dict) -> str:
        """Determine what style of greeting to generate"""
        mem_type = memory['memory_type']
        importance = float(memory.get('importance', 0.5))
        
        if mem_type == 'project':
            return 'project_checkin'
        elif importance > 0.8 and mem_type == 'episodic':
            return 'emotional_support'
        else:
            return 'topic_checkin'
    
    def record_checkin(self, user_id: str, memory_id: int, response_status: str = 'sent'):
        """
        Record that we've sent a check-in about this memory
        
        Args:
            user_id: User identifier
            memory_id: Memory we checked in about
            response_status: 'sent' or 'responded' (if user has already responded)
        """
        if user_id not in self.recent_checkins:
            self.recent_checkins[user_id] = []
        
        self.recent_checkins[user_id].append((memory_id, datetime.now(), response_status))
        
        # Keep only last 20 check-ins per user
        if len(self.recent_checkins[user_id]) > 20:
            self.recent_checkins[user_id] = self.recent_checkins[user_id][-20:]
    
    def mark_checkin_responded(self, user_id: str, memory_id: int):
        """Mark that user has responded to a check-in"""
        if user_id not in self.recent_checkins:
            return
        
        # Find the most recent check-in about this memory and mark as responded
        for i, (mid, timestamp, status) in enumerate(self.recent_checkins[user_id]):
            if mid == memory_id:
                self.recent_checkins[user_id][i] = (mid, timestamp, 'responded')
    
    def reactivate_dormant_memory(self, memory_id: int):
        """
        Reactivate a dormant memory when user brings it up
        
        This increases its accessibility for future proactive retrieval
        """
        memory = self.memory_store.get_memory(memory_id)
        
        if memory and memory['status'] == 'active':
            # Update the last_accessed timestamp to make it recent again
            self.memory_store.update_memory(
                memory_id,
                status='active'  # Ensure it's active
            )
            
            # The _log_access method will update last_accessed and access_count
            self.memory_store._log_access(memory_id)
            
            print(f"[MEMORY] Reactivated dormant memory {memory_id}: {memory['content'][:50]}...")
    
    def _parse_timestamp(self, ts) -> datetime:
        """Parse timestamp from various formats"""
        if isinstance(ts, datetime):
            return ts
        
        if isinstance(ts, str):
            try:
                return datetime.fromisoformat(ts)
            except:
                try:
                    return datetime.strptime(ts, '%Y-%m-%d %H:%M:%S')
                except:
                    return datetime.now()
        
        return datetime.now()


# Global instance
_proactive_greeting_manager = None

def init_proactive_greeting_manager(memory_store, memory_retriever):
    """Initialize the proactive greeting manager"""
    global _proactive_greeting_manager
    _proactive_greeting_manager = ProactiveGreetingManager(memory_store, memory_retriever)
    print("[PROACTIVE] Proactive greeting system initialized")
    return _proactive_greeting_manager

def get_proactive_greeting_manager() -> Optional[ProactiveGreetingManager]:
    """Get the proactive greeting manager instance"""
    global _proactive_greeting_manager
    return _proactive_greeting_manager
