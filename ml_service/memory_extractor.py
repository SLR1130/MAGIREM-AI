"""
Memory Extractor - Determines what information is worth storing as long-term memory
Uses heuristics and semantic analysis to identify meaningful information
"""

import re
from typing import Dict, List, Optional, Tuple

class MemoryExtractor:
    """
    Analyzes user messages to extract memory-worthy information.
    Determines: memory type, importance, confidence
    """
    
    def __init__(self):
        """Initialize patterns for memory extraction"""
        
        # Patterns for explicit memory requests
        self.explicit_memory_patterns = [
            r"remember\s+(?:that\s+)?(?:i\s+)?(.+?)(?:\.|$)",
            r"(?:please\s+)?remember\s+this",
            r"save\s+this",
            r"don't\s+forget\s+(?:that\s+)?(?:i\s+)?(.+?)(?:\.|$)",
            r"keep\s+(?:this\s+)?in\s+mind",
            r"remember\s+this\s+.*?:\s*(.+?)(?:\.|$)",
        ]
        
        # Patterns for project/work mentions
        self.project_patterns = [
            r"(?:i'?m|i\s+am)\s+(?:working|building|developing|creating|making|coding)\s+(?:on\s+)?(?:a\s+)?(?:project\s+called\s+)?(?:named\s+)?(['\"]?)(\w+(?:\s+\w+)*)\1",
            r"(?:my\s+)?project[:\s]+(['\"]?)(\w+(?:\s+\w+)*)\1",
            r"(?:my\s+)?(?:app|application|system|tool|library|framework)[:\s]+(['\"]?)(\w+(?:\s+\w+)*)\1",
            r"(?:i\s+)?(?:created|built|made)\s+(['\"]?)(\w+(?:\s+\w+)*)\1",
            r"([A-Z][a-z]+(?:[A-Z][a-z]+)*)\s+(?:is|was)\s+(?:a\s+)?(?:project|app|system|tool|library)",
        ]
        
        # Patterns for preferences/habits
        self.preference_patterns = [
            r"(?:i\s+)?(?:prefer|like|prefer|prefer|love)\s+(.+?)(?:\.|,|$)",
            r"(?:i\s+)?(?:don't|don't)\s+like\s+(.+?)(?:\.|,|$)",
            r"(?:i\s+)?(?:prefer|usually|always|typically)\s+(.+?)(?:\.|,|$)",
            r"my\s+(?:favorite|preferred)\s+(.+?)(?:\.|,|$)",
        ]
        
        # Patterns for status/progress
        self.progress_patterns = [
            r"(?:i'?m|i\s+am)\s+(?:stuck|blocked|struggling)\s+(?:with|on)\s+(.+?)(?:\.|,|$)",
            r"(?:i\s+(?:just\s+)?(?:finished|completed|done)\s+(?:with\s+)?)?(.+?)(?:\s+(?:finished|completed|done)|\.|\?|!|,|$)",
            r"(?:working\s+on|at|with)\s+(.+?)(?:\.|,|$)",
            r"(?:progress|update)[:\s]+(.+?)(?:\.|,|$)",
        ]
        
        # Emotional/personal context
        self.emotional_patterns = [
            r"(?:i'?m|i\s+am)\s+feeling\s+(.+?)(?:\.|,|$)",
            r"(?:i'?m|i\s+am)\s+(?:going through|dealing with)\s+(.+?)(?:\.|,|$)",
            r"(?:struggling|stressed|anxious)\s+(?:about|with)\s+(.+?)(?:\.|,|$)",
        ]
        
        # COMPILED PATTERNS - Pre-compile all patterns for performance and early validation
        self._compiled_patterns = self._compile_all_patterns()
    
    def _compile_all_patterns(self):
        """Compile all regex patterns upfront to catch errors early"""
        compiled = {
            'explicit': [],
            'project': [],
            'preference': [],
            'progress': [],
            'emotional': []
        }
        
        patterns_by_group = {
            'explicit': self.explicit_memory_patterns,
            'project': self.project_patterns,
            'preference': self.preference_patterns,
            'progress': self.progress_patterns,
            'emotional': self.emotional_patterns,
        }
        
        for group_name, patterns in patterns_by_group.items():
            for pattern in patterns:
                try:
                    compiled[group_name].append(re.compile(pattern, re.IGNORECASE))
                except re.error as e:
                    print(f"[REGEX_ERROR] Pattern compilation failed in {group_name}: {e}")
                    print(f"[REGEX_ERROR] Pattern was: {pattern}")
                    raise
        
        return compiled
    
    def extract(self, user_message: str, emotion: str = None) -> List[Dict]:
        """
        Extract all memory-worthy information from a message
        
        Returns:
            List of memory objects with {type, content, importance, confidence}
        """
        memories = []
        
        # Check for explicit memory requests (highest priority)
        explicit = self._extract_explicit_memories(user_message)
        if explicit:
            memories.extend(explicit)
        
        # Extract project information
        projects = self._extract_projects(user_message)
        if projects:
            memories.extend(projects)
        
        # Extract preferences
        preferences = self._extract_preferences(user_message)
        if preferences:
            memories.extend(preferences)
        
        # Extract progress/status
        progress = self._extract_progress(user_message, projects)
        if progress:
            memories.extend(progress)
        
        # Extract emotional context (if relevant)
        if emotion and emotion in ['sadness', 'anxiety', 'anger', 'stress']:
            emotional = self._extract_emotional_context(user_message)
            if emotional:
                memories.extend(emotional)
        
        # Filter out trivial memories
        memories = [m for m in memories if self._is_meaningful(m, user_message)]
        
        return memories
    
    def _extract_explicit_memories(self, message: str) -> List[Dict]:
        """Extract explicitly requested memories"""
        memories = []
        
        for pattern in self.explicit_memory_patterns:
            matches = re.finditer(pattern, message, re.IGNORECASE)
            for match in matches:
                # Get the content (may be in a group)
                if match.groups():
                    content = match.group(1) if match.lastindex >= 1 else match.group(0)
                else:
                    content = match.group(0)
                
                if self._is_meaningful_text(content):
                    memories.append({
                        'type': 'semantic',  # Explicit requests are semantic (facts)
                        'content': content.strip(),
                        'importance': 0.95,  # Very high - user explicitly requested
                        'confidence': 0.95,
                        'source': 'explicit_request'
                    })
        
        return memories
    
    def _extract_projects(self, message: str) -> List[Dict]:
        """Extract project/work mentions"""
        memories = []
        
        for pattern in self.project_patterns:
            matches = re.finditer(pattern, message, re.IGNORECASE)
            for match in matches:
                # Extract project name (handling groups from regex)
                groups = match.groups()
                project_name = None
                
                if len(groups) >= 2:
                    project_name = groups[1]  # Usually group 2 after quotes
                elif len(groups) >= 1:
                    project_name = groups[0]
                else:
                    project_name = match.group(0)
                
                if project_name and self._is_meaningful_text(project_name):
                    # Check if it's a genuine project name (not just common words)
                    if not self._is_trivial_term(project_name):
                        memories.append({
                            'type': 'project',
                            'content': f"User is developing a project: {project_name.strip()}",
                            'project_name': project_name.strip(),
                            'importance': 0.85,  # High importance
                            'confidence': 0.9,
                            'source': 'project_mention'
                        })
        
        return memories
    
    def _extract_preferences(self, message: str) -> List[Dict]:
        """Extract user preferences"""
        memories = []
        
        for pattern in self.preference_patterns:
            matches = re.finditer(pattern, message, re.IGNORECASE)
            for match in matches:
                preference = match.group(1) if match.lastindex else match.group(0)
                
                if self._is_meaningful_text(preference) and len(preference) > 5:
                    memories.append({
                        'type': 'preference',
                        'content': f"User preference: {preference.strip()}",
                        'importance': 0.7,
                        'confidence': 0.8,
                        'source': 'preference_mention'
                    })
        
        return memories
    
    def _extract_progress(self, message: str, projects: List[Dict]) -> List[Dict]:
        """Extract progress/status updates"""
        memories = []
        
        # Look for status/progress keywords
        has_project = any(p['type'] == 'project' for p in projects)
        
        for pattern in self.progress_patterns:
            matches = re.finditer(pattern, message, re.IGNORECASE)
            for match in matches:
                progress_text = match.group(1) if match.lastindex else match.group(0)
                
                if self._is_meaningful_text(progress_text):
                    # Create memory for any progress update, not just when project mentioned
                    memories.append({
                        'type': 'episodic',
                        'content': f"User progress: completed or working on {progress_text.strip()}",
                        'importance': 0.70 if has_project else 0.65,
                        'confidence': 0.85,
                        'source': 'progress_update'
                    })
        
        return memories
    
    def _extract_emotional_context(self, message: str) -> List[Dict]:
        """Extract emotional context (for repeated issues)"""
        memories = []
        
        for pattern in self.emotional_patterns:
            matches = re.finditer(pattern, message, re.IGNORECASE)
            for match in matches:
                emotional_text = match.group(1) if match.lastindex else match.group(0)
                
                if self._is_meaningful_text(emotional_text) and len(emotional_text) > 5:
                    memories.append({
                        'type': 'semantic',
                        'content': f"Emotional context: {emotional_text.strip()}",
                        'importance': 0.65,
                        'confidence': 0.75,
                        'source': 'emotional_mention'
                    })
        
        return memories
    
    def _is_meaningful(self, memory: Dict, full_message: str) -> bool:
        """
        Determine if a memory is actually worth storing
        Filters out noise and trivial information
        """
        content = memory.get('content', '')
        
        # Minimum length requirement
        if len(content) < 5:
            return False
        
        # Reject if it's just greetings or pleasantries
        if len(content) < 20 and self._is_trivial_term(content):
            return False
        
        # Reject greetings alone ("hey", "hi", "lol", etc)
        trivial = ['hey', 'hi', 'hello', 'yo', 'bro', 'dude', 'nice', 'cool', 'lol', 'haha', 'okay', 'ok', 'yeah', 'yes', 'no']
        if content.lower() in trivial:
            return False
        
        # Reject generic statements
        if len(content) < 30:
            # For short content, make sure it's specific, not generic
            generic = ['nothing much', 'not much', 'dunno', 'not sure', 'maybe', 'idk', 'i guess']
            if any(g in content.lower() for g in generic):
                return False
        
        return True
    
    def _is_meaningful_text(self, text: str) -> bool:
        """Check if text is meaningful (not just punctuation/noise)"""
        if not text:
            return False
        
        # Remove punctuation for evaluation
        clean = re.sub(r'[^\w\s]', '', text).strip()
        
        return len(clean) > 3
    
    def _is_trivial_term(self, term: str) -> bool:
        """Check if a term is too generic/trivial"""
        trivial = [
            'thing', 'stuff', 'whatever', 'something', 'anything'
        ]
        
        term_lower = term.lower().strip()
        # Exact match only for very generic terms
        return term_lower in trivial
    
    def should_summarize_conversation(self, message_count: int) -> bool:
        """Determine if a conversation is long enough to summarize"""
        # Summarize after ~20+ messages to reduce context
        return message_count >= 20
    
    def score_memory_importance(self,
                               memory: Dict,
                               emotion: str = None,
                               user_explicit: bool = False) -> float:
        """
        Adjust importance score based on context
        """
        importance = memory.get('importance', 0.5)
        
        # Boost if explicitly requested
        if user_explicit:
            importance = min(1.0, importance + 0.15)
        
        # Boost if related to emotional content
        if emotion in ['sadness', 'anxiety', 'stress']:
            if memory['type'] in ['semantic', 'episodic']:
                importance = min(1.0, importance + 0.10)
        
        # Boost projects
        if memory['type'] == 'project':
            importance = min(1.0, importance + 0.05)
        
        return importance
    
    def extract_topics(self, message: str) -> List[str]:
        """Extract main topics from a message for summarization"""
        topics = []
        
        # Find capitalized terms (likely important nouns)
        capitalized = re.findall(r'\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b', message)
        topics.extend(capitalized)
        
        # Extract project names
        projects = self._extract_projects(message)
        for p in projects:
            if 'project_name' in p:
                topics.append(p['project_name'])
        
        # Remove duplicates and keep unique
        return list(set(topics))[:5]  # Limit to top 5
