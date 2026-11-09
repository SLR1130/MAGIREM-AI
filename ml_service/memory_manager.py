"""
Memory Manager - Orchestrates the entire long-term memory system
Coordinates extraction, storage, and retrieval of memories
Enhanced with proactive greeting capability
"""

import threading
from typing import Dict, List, Optional
from datetime import datetime
import json

from memory_store import MemoryStore
from memory_extractor import MemoryExtractor
from memory_retriever import MemoryRetriever
from proactive_greeting_manager import ProactiveGreetingManager

class MemoryManager:
    """
    Main orchestrator for long-term memory system.
    Handles the full lifecycle: extraction -> storage -> retrieval -> proactive greetings
    """
    
    def __init__(self, db_path: str = './memory_store.db'):
        """Initialize memory system components"""
        self.store = MemoryStore(db_path)
        self.extractor = MemoryExtractor()
        self.retriever = MemoryRetriever(self.store)
        self.proactive_manager = ProactiveGreetingManager(self.store, self.retriever)
        
        # Background processing thread (optional)
        self._processing_thread = None
        self._should_stop = False
    
    def process_message(self,
                       user_id: str,
                       message: str,
                       emotion: str = None,
                       conversation_history: List[Dict] = None) -> Dict:
        """
        Process a user message for memory extraction
        Called after the message is received but before response generation
        
        Args:
            user_id: User identifier
            message: User's message
            emotion: Detected emotion (optional)
            conversation_history: Recent conversation (optional)
        
        Returns:
            {
                'memories_extracted': [list of memory objects],
                'memory_ids': [IDs of stored memories],
                'processing_time': time taken
            }
        """
        import time
        start_time = time.time()
        
        # Extract memories from message
        extracted_memories = self.extractor.extract(message, emotion)
        
        # Filter for meaningful content
        meaningful_memories = [m for m in extracted_memories if self._is_storage_worthy(m)]
        
        # Store memories
        stored_ids = []
        for memory in meaningful_memories:
            memory_id = self.store.create_memory(
                user_id=user_id,
                memory_type=memory['type'],
                content=memory['content'],
                importance=memory.get('importance', 0.5),
                confidence=memory.get('confidence', 0.7),
                metadata={
                    'source': memory.get('source', 'message'),
                    'emotion': emotion
                },
                source_message=message
            )
            
            if memory_id:
                # Handle specific memory types
                if memory['type'] == 'project' and 'project_name' in memory:
                    self._create_project_memory(user_id, memory)
                
                elif memory['type'] == 'preference':
                    self._store_preference(user_id, memory)
                
                stored_ids.append(memory_id)
        
        processing_time = time.time() - start_time
        
        return {
            'memories_extracted': extracted_memories,
            'memories_stored': stored_ids,
            'count': len(stored_ids),
            'processing_time': processing_time
        }
    
    def retrieve_context(self,
                        user_id: str,
                        current_message: str,
                        conversation_history: List[Dict] = None,
                        max_memories: int = 5,
                        max_tokens: int = 500) -> Dict:
        """
        Retrieve relevant memories to inform the response
        Called before generating a response to the user
        
        Args:
            user_id: User identifier
            current_message: Current user message
            conversation_history: Recent conversation
            max_memories: Maximum number of memories to return
            max_tokens: Token budget for memory context
        
        Returns:
            {
                'memories': [relevant memory objects],
                'projects': [active projects],
                'preferences': {user preferences},
                'context_text': formatted text for LLM,
                'token_count': approx tokens used
            }
        """
        retrieval_result = self.retriever.retrieve_relevant_memories(
            user_id=user_id,
            current_message=current_message,
            conversation_history=conversation_history,
            max_results=max_memories,
            max_tokens=max_tokens
        )
        
        # Format memories for LLM context
        context_text = self._format_context(retrieval_result)
        
        return {
            **retrieval_result,
            'context_text': context_text
        }
    
    def handle_message_pair(self,
                           user_id: str,
                           user_message: str,
                           ai_response: str,
                           emotion: str = None,
                           conversation_history: List[Dict] = None):
        """
        Full processing of a conversation exchange
        Extract memories from user message, then retrieve context for next responses
        """
        # Process memory extraction
        extraction_result = self.process_message(
            user_id,
            user_message,
            emotion,
            conversation_history
        )
        
        print(f"[MEMORY] Extracted {extraction_result['count']} memories in {extraction_result['processing_time']:.2f}s")
        
        return extraction_result
    
    def update_project_status(self,
                             user_id: str,
                             project_name: str,
                             new_status: str,
                             progress: str = None) -> bool:
        """
        Update project status when user mentions progress/change
        """
        # Find project
        projects = self.store.get_active_projects(user_id)
        
        for project in projects:
            if project_name.lower() in project['project_name'].lower() or \
               project['project_name'].lower() in project_name.lower():
                
                self.store.update_project_status(
                    project['id'],
                    new_status,
                    progress
                )
                return True
        
        return False
    
    def add_explicit_memory(self,
                           user_id: str,
                           memory_content: str,
                           memory_type: str = 'semantic',
                           importance: float = 0.9) -> int:
        """
        Add an explicitly requested memory (e.g., "remember this")
        """
        memory_id = self.store.create_memory(
            user_id=user_id,
            memory_type=memory_type,
            content=memory_content,
            importance=importance,
            confidence=0.95,
            metadata={'source': 'explicit_request'}
        )
        
        print(f"[MEMORY] Created explicit memory: {memory_id}")
        
        return memory_id
    
    def detect_contradictions(self,
                             user_id: str,
                             new_memory: Dict) -> List[Dict]:
        """
        Detect if new memory contradicts existing memories
        Returns list of potentially conflicting memories
        """
        all_memories = self.store.get_user_memories(user_id)
        
        conflicts = []
        new_content = new_memory['content'].lower()
        
        for memory in all_memories:
            # Check for contradictory status changes
            if memory['memory_type'] == 'project' and new_memory['memory_type'] == 'project':
                if self._detect_project_status_change(memory, new_memory):
                    conflicts.append(memory)
            
            # Check for direct contradictions
            if self._detect_contradiction(memory['content'], new_content):
                conflicts.append(memory)
        
        return conflicts
    
    def get_proactive_greeting(self,
                             user_id: str,
                             conversation_history: List[Dict] = None) -> Dict:
        """
        Attempt to generate a proactive greeting based on recent memories
        
        Returns:
            {
                'should_greet': bool,
                'greeting': str (if should_greet=True),
                'memory_context': memory object,
                'style': str,
                'confidence': float,
                'reason': str
            }
        """
        
        # Check if we should proactively greet
        decision = self.proactive_manager.should_proactively_greet(
            user_id,
            conversation_history
        )
        
        result = {
            'should_greet': decision['should_greet'],
            'confidence': decision['confidence'],
            'reason': decision['reasoning'],
            'suppress_reason': decision.get('suppress_reason'),
            'greeting': None,
            'memory_context': decision.get('topic_memory'),
            'style': decision.get('greeting_style'),
        }
        
        # If we shouldn't greet, return early
        if not decision['should_greet']:
            return result
        
        # Generate the actual greeting using LLM
        try:
            from proactive_greeting_generator import generate_proactive_greeting
            
            greeting_result = generate_proactive_greeting(
                decision['topic_memory'],
                decision['greeting_style'],
                conversation_history[-1] if conversation_history else None
            )
            
            if greeting_result.get('success'):
                result['greeting'] = greeting_result.get('greeting')
                result['method'] = greeting_result.get('method')
                
                # Record that we're sending this check-in
                self.proactive_manager.record_checkin(
                    user_id,
                    decision['topic_memory']['id'],
                    response_status='sent'
                )
                
                print(f"[PROACTIVE_GREETING] Generated for user {user_id}: {result['greeting'][:50]}...")
            else:
                result['should_greet'] = False
                result['reason'] = 'Failed to generate greeting'
        
        except Exception as e:
            print(f"[PROACTIVE_GREETING_ERROR] {e}")
            result['should_greet'] = False
            result['reason'] = f'Error: {str(e)}'
        
        return result
    
    def consolidate_memories(self, user_id: str) -> Dict:
        """
        Consolidate and clean up memories
        - Merge duplicates
        - Deactivate old low-importance memories
        - Create summaries of old conversations
        """
        results = {
            'duplicates_merged': 0,
            'old_memories_archived': 0,
            'consolidation_time': 0
        }
        
        import time
        start = time.time()
        
        # Get all memories
        memories = self.store.get_user_memories(user_id)
        
        # Look for potential duplicates
        # (Simple approach: similar content and type)
        checked = set()
        for i, memory1 in enumerate(memories):
            if memory1['id'] in checked:
                continue
            
            for memory2 in memories[i+1:]:
                if memory2['id'] in checked:
                    continue
                
                # Check similarity
                if self._are_similar_memories(memory1, memory2):
                    # Merge
                    self.store.merge_memories(memory1['id'], memory2['id'])
                    results['duplicates_merged'] += 1
                    checked.add(memory2['id'])
        
        # Archive old memories
        archived = self.store.cleanup_old_memories(days=90)
        results['old_memories_archived'] = archived
        
        results['consolidation_time'] = time.time() - start
        
        return results
    
    def get_memory_stats(self, user_id: str) -> Dict:
        """Get statistics about user's memory"""
        memories = self.store.get_user_memories(user_id)
        projects = self.store.get_active_projects(user_id)
        
        return {
            'total_memories': len(memories),
            'by_type': self._count_by_type(memories),
            'active_projects': len(projects),
            'projects': projects,
            'memory_summary': self.retriever.get_memory_summary(user_id)
        }
    
    # Helper methods
    
    def _is_storage_worthy(self, memory: Dict) -> bool:
        """Decide if memory should be stored"""
        # Must have minimum importance
        if memory.get('importance', 0) < 0.5:
            return False
        
        # Content must be meaningful
        content = memory.get('content', '')
        if len(content) < 5:
            return False
        
        return True
    
    def _create_project_memory(self, user_id: str, memory: Dict):
        """Store project-specific memory"""
        project_name = memory.get('project_name', '')
        
        self.store.create_project_memory(
            user_id=user_id,
            project_name=project_name,
            description=memory['content'],
            project_type='software'  # Default, can be detected
        )
    
    def _store_preference(self, user_id: str, memory: Dict):
        """Store user preference"""
        # Extract key and value from content
        content = memory['content']
        
        # Simple extraction: "User preference: X" -> key=X
        if 'preference:' in content.lower():
            parts = content.split(':', 1)
            if len(parts) == 2:
                key = f"preference_{len(self.store.get_preferences(user_id))}"
                self.store.set_preference(
                    user_id=user_id,
                    key=key,
                    value=parts[1].strip(),
                    confidence=memory.get('confidence', 0.8)
                )
    
    def _format_context(self, retrieval_result: Dict) -> str:
        """
        Format retrieved memories into context text for LLM
        This is what gets fed to Ollama as additional context
        """
        lines = []
        
        # Add relevant memories
        if retrieval_result['memories']:
            lines.append("[RELEVANT MEMORIES]")
            for i, memory in enumerate(retrieval_result['memories'][:3], 1):
                score = memory.get('relevance_score', 0)
                lines.append(f"- {memory['content']} (relevance: {score:.0%})")
        
        # Add active projects
        if retrieval_result['projects']:
            lines.append("\n[YOUR PROJECTS]")
            for project in retrieval_result['projects'][:3]:
                status = project.get('status', 'unknown')
                lines.append(f"- {project['project_name']} ({status})")
                if project.get('last_known_progress'):
                    lines.append(f"  Latest: {project['last_known_progress']}")
        
        # Add preferences
        if retrieval_result['preferences']:
            lines.append("\n[YOUR PREFERENCES]")
            for key, value in list(retrieval_result['preferences'].items())[:3]:
                lines.append(f"- {key}: {value}")
        
        return '\n'.join(lines)
    
    def _detect_project_status_change(self, old_memory: Dict, new_memory: Dict) -> bool:
        """Check if project status has changed"""
        if old_memory['memory_type'] != 'project' or new_memory['memory_type'] != 'project':
            return False
        
        # Look for status keywords
        status_keywords = ['abandoned', 'completed', 'done', 'finished', 'inactive', 'paused']
        
        new_content_lower = new_memory['content'].lower()
        old_project_name = old_memory.get('metadata', {})
        
        for keyword in status_keywords:
            if keyword in new_content_lower:
                return True
        
        return False
    
    def _detect_contradiction(self, text1: str, text2: str) -> bool:
        """Simple contradiction detection"""
        # Look for common contradiction patterns
        patterns = [
            ('I use', 'I switched from', 'I stopped using'),
            ('I like', "I don't like", 'I hate'),
            ('working on', 'stopped working on', 'abandoned'),
        ]
        
        text1_lower = text1.lower()
        text2_lower = text2.lower()
        
        # Very simple heuristic: if both mention the same entity but with opposite sentiment
        # This is a simplified check - real contradiction detection would be more complex
        
        return False  # Placeholder
    
    def _are_similar_memories(self, mem1: Dict, mem2: Dict) -> bool:
        """Check if two memories are similar (potential duplicates)"""
        if mem1['memory_type'] != mem2['memory_type']:
            return False
        
        # Simple Levenshtein-like check
        content1 = mem1['content'].lower()
        content2 = mem2['content'].lower()
        
        # Check if one contains significant portion of the other
        if len(content1) > 20 and len(content2) > 20:
            # More than 70% overlap suggests duplicate
            overlap = sum(1 for c in content1 if c in content2)
            ratio = overlap / max(len(content1), len(content2))
            return ratio > 0.7
        
        return False
    
    def _count_by_type(self, memories: List[Dict]) -> Dict[str, int]:
        """Count memories by type"""
        counts = {}
        for memory in memories:
            mem_type = memory['memory_type']
            counts[mem_type] = counts.get(mem_type, 0) + 1
        return counts
    
    def close(self):
        """Clean up resources"""
        if self.store:
            self.store.close()
    
    def __del__(self):
        """Cleanup on destruction"""
        self.close()
