"""
Memory Retriever - Finds relevant long-term memories for the current conversation
Uses hybrid approach: semantic similarity + recency + importance + frequency
"""

import re
from typing import Dict, List, Optional, Tuple
from datetime import datetime, timedelta
import math

class MemoryRetriever:
    """
    Retrieves relevant memories for context-aware responses.
    Uses multiple signals to rank memory relevance.
    """
    
    def __init__(self, memory_store):
        """
        Initialize retriever with memory store
        
        Args:
            memory_store: MemoryStore instance for accessing memories
        """
        self.memory_store = memory_store
    
    def retrieve_relevant_memories(self,
                                  user_id: str,
                                  current_message: str,
                                  conversation_history: List[Dict] = None,
                                  max_results: int = 5,
                                  max_tokens: int = 500) -> Dict:
        """
        Retrieve the most relevant memories for the current context
        
        Args:
            user_id: User identifier
            current_message: Current user message
            conversation_history: Recent conversation history
            max_results: Maximum number of memories to return
            max_tokens: Token budget for memory context
        
        Returns:
            {
                'memories': [memory objects],
                'projects': [active projects],
                'preferences': {preference dict},
                'summary': conversation summary if relevant,
                'token_estimate': approximate tokens used
            }
        """
        result = {
            'memories': [],
            'projects': [],
            'preferences': {},
            'summary': None,
            'token_estimate': 0
        }
        
        # Get all active memories for user
        all_memories = self.memory_store.get_user_memories(user_id, status='active')
        
        if not all_memories:
            # Still get preferences and projects
            result['projects'] = self.memory_store.get_active_projects(user_id)
            result['preferences'] = self.memory_store.get_preferences(user_id)
            return result
        
        # Rank memories by relevance
        ranked_memories = self._rank_memories(
            current_message,
            all_memories,
            conversation_history
        )
        
        # Select top memories within token budget
        selected_memories = []
        token_count = 0
        
        for memory in ranked_memories[:max_results]:
            tokens = self._estimate_tokens(memory['content'])
            
            if token_count + tokens <= max_tokens:
                selected_memories.append(memory)
                token_count += tokens
            else:
                break  # Budget exhausted
        
        result['memories'] = selected_memories
        result['projects'] = self.memory_store.get_active_projects(user_id)
        result['preferences'] = self.memory_store.get_preferences(user_id)
        result['token_estimate'] = token_count
        
        return result
    
    def _rank_memories(self,
                      current_message: str,
                      memories: List[Dict],
                      conversation_history: List[Dict] = None) -> List[Dict]:
        """
        Rank memories by relevance using multiple signals
        """
        scored_memories = []
        
        for memory in memories:
            # Skip inactive/merged memories
            if memory.get('status') not in ['active']:
                continue
            
            # Calculate relevance score
            score = self._calculate_relevance(
                current_message,
                memory,
                conversation_history
            )
            
            # Skip very low-scoring memories
            if score < 0.2:
                continue
            
            scored_memories.append({
                **memory,
                'relevance_score': score
            })
        
        # Sort by relevance score (descending)
        scored_memories.sort(key=lambda x: x['relevance_score'], reverse=True)
        
        return scored_memories
    
    def _calculate_relevance(self,
                            current_message: str,
                            memory: Dict,
                            conversation_history: List[Dict] = None) -> float:
        """
        Calculate relevance score combining multiple signals
        
        Signals:
        - Semantic similarity (content match)
        - Importance score
        - Recency (with dormancy awareness)
        - Access frequency
        - Memory type appropriateness
        """
        
        # 1. Semantic similarity (0.0-1.0)
        semantic_sim = self._semantic_similarity(current_message, memory['content'])
        
        # 2. Conversation relevance (is memory type relevant?)
        conv_relevance = self._conversation_relevance(memory, conversation_history)
        
        # 3. Recency factor with dormancy awareness
        # This is REACTIVE retrieval - don't suppress old memories when user asks for them
        recency = self._recency_score(memory)
        
        # 4. Importance of memory
        importance = float(memory.get('importance', 0.5))
        
        # 5. Frequency of use
        access_freq = self._access_frequency_score(memory)
        
        # 6. Confidence in the memory
        confidence = float(memory.get('confidence', 0.7))
        
        # Weighted combination
        # Semantic similarity is most important
        relevance = (
            semantic_sim * 0.40 +           # Semantic is primary signal
            importance * 0.20 +             # Importance of the memory
            recency * 0.15 +                # Recency helps, but doesn't eliminate old memories
            access_freq * 0.10 +            # Frequent use is good
            confidence * 0.10 +             # Confidence in accuracy
            conv_relevance * 0.05           # Conversation context relevance
        )
        
        return relevance
    
    def _semantic_similarity(self, message: str, memory_content: str) -> float:
        """
        Calculate semantic similarity between message and memory
        Uses keyword matching and phrase similarity
        """
        # Normalize texts
        msg_lower = message.lower()
        mem_lower = memory_content.lower()
        
        # Exact substring match is high confidence
        if mem_lower in msg_lower or msg_lower in mem_lower:
            return 1.0
        
        # Extract key phrases/terms from memory
        memory_terms = self._extract_key_terms(memory_content)
        message_terms = self._extract_key_terms(message)
        
        if not memory_terms:
            return 0.0
        
        # Count matching terms
        matches = sum(1 for term in memory_terms if term in message_terms or term in msg_lower)
        
        # Calculate jaccard similarity
        jaccard = matches / len(memory_terms)
        
        # Boost score if important keywords match
        if self._has_project_reference(memory_content, message):
            jaccard = min(1.0, jaccard + 0.3)
        
        return min(1.0, jaccard)
    
    def _conversation_relevance(self, memory: Dict, conversation_history: List[Dict] = None) -> float:
        """
        Score memory based on conversation context
        """
        memory_type = memory.get('memory_type', 'semantic')
        
        if not conversation_history or len(conversation_history) == 0:
            # Project memories always relevant
            if memory_type == 'project':
                return 0.8
            return 0.3
        
        # If conversation is emotional, emotional memories more relevant
        recent_emotions = [m.get('emotion', 'neutral') for m in conversation_history[-3:]]
        
        if any(e in ['sadness', 'anxiety', 'stress'] for e in recent_emotions):
            if memory_type in ['semantic', 'episodic']:
                return 0.7
        
        # Project memories relevant if working on something
        if memory_type == 'project':
            if any('work' in m.get('user', '').lower() or 'project' in m.get('user', '').lower() 
                   for m in conversation_history[-2:]):
                return 0.9
            return 0.6
        
        return 0.5
    
    def _recency_score(self, memory: Dict) -> float:
        """
        Score based on recency, with decay for old memories
        But DON'T eliminate old important memories
        """
        created_at = memory.get('created_at')
        if not created_at:
            return 0.5
        
        # Parse timestamp
        try:
            if isinstance(created_at, str):
                created = datetime.fromisoformat(created_at)
            else:
                created = created_at
        except:
            return 0.5
        
        days_old = (datetime.now() - created).days
        
        # Decay function: recent memories score high, very old ones decay
        # But important memories (importance > 0.7) decay slower
        importance = memory.get('importance', 0.5)
        
        # Base decay: 1.0 if today, 0.5 if 30 days old, 0.2 if 90+ days old
        base_decay = max(0.1, 1.0 - (days_old / 90.0) * 0.9)
        
        # For important memories, slow decay
        if importance > 0.7:
            base_decay = max(0.3, base_decay)
        
        return base_decay
    
    def _access_frequency_score(self, memory: Dict) -> float:
        """
        Score based on how often this memory has been retrieved/used
        Memories that are retrieved more often are probably more relevant
        """
        access_count = memory.get('access_count', 0)
        
        # Logarithmic scale: 0->0.1, 5->0.5, 20->0.8, 100->0.95
        if access_count == 0:
            return 0.1
        
        return min(0.95, 0.1 + (math.log(access_count + 1) / 5.0))
    
    def _extract_key_terms(self, text: str, max_terms: int = 10) -> List[str]:
        """
        Extract important keywords/terms from text
        Prioritizes named entities and domain-specific terms
        """
        terms = []
        
        # Extract capitalized terms (likely names/entities)
        capitalized = re.findall(r'\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b', text)
        terms.extend(capitalized)
        
        # Extract quoted terms
        quoted = re.findall(r'["\']([^"\']+)["\']', text)
        terms.extend(quoted)
        
        # Extract important words (nouns, verbs ending in -ing)
        important = re.findall(r'\b([A-Z][a-z]+|(?:work|project|build|develop|code|app|system|tool|library|framework)\w*)\b', text, re.IGNORECASE)
        terms.extend(important)
        
        # Remove duplicates and limit
        terms = list(set(t.lower() for t in terms))[:max_terms]
        
        return terms
    
    def _has_project_reference(self, memory_content: str, message: str) -> bool:
        """
        Check if memory and message both reference projects/work
        """
        project_keywords = ['project', 'building', 'developing', 'creating', 'app', 'system', 'tool', 'code', 'work']
        
        memory_has_project = any(kw in memory_content.lower() for kw in project_keywords)
        message_has_project = any(kw in message.lower() for kw in project_keywords)
        
        return memory_has_project and message_has_project
    
    def _estimate_tokens(self, text: str) -> int:
        """Estimate token count (rough approximation)"""
        # Approximate: 1 token per 4 characters
        return max(1, len(text) // 4)
    
    def search_memories(self,
                       user_id: str,
                       query: str,
                       memory_type: str = None) -> List[Dict]:
        """
        Search memories by query string
        """
        all_memories = self.memory_store.get_user_memories(user_id, memory_type)
        
        results = []
        for memory in all_memories:
            if query.lower() in memory['content'].lower():
                results.append(memory)
        
        return sorted(results, key=lambda x: x['importance'], reverse=True)
    
    def get_memory_summary(self, user_id: str) -> Dict:
        """
        Get a summary of all memories for a user
        """
        memories = self.memory_store.get_user_memories(user_id)
        
        summary = {
            'total_memories': len(memories),
            'by_type': {},
            'high_importance': [],
            'recent_memories': [],
            'most_accessed': []
        }
        
        # Group by type
        for memory in memories:
            mem_type = memory['memory_type']
            if mem_type not in summary['by_type']:
                summary['by_type'][mem_type] = 0
            summary['by_type'][mem_type] += 1
        
        # High importance memories
        summary['high_importance'] = [
            m for m in memories if m['importance'] > 0.8
        ][:5]
        
        # Recent memories
        summary['recent_memories'] = sorted(
            memories,
            key=lambda x: x.get('created_at', ''),
            reverse=True
        )[:5]
        
        # Most accessed
        summary['most_accessed'] = sorted(
            memories,
            key=lambda x: x.get('access_count', 0),
            reverse=True
        )[:5]
        
        return summary
