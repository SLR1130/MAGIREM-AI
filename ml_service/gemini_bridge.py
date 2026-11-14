"""
Gemini Bridge for MAGIREM AI
Provides high-speed, native Tagalog/Taglish conversational and crisis assessment
using Google Gemini API with seamless fallback to local Ollama.
"""

import os
import json
import time
import requests
from typing import List, Dict, Any, Optional

class GeminiMockResponse:
    """Mock response that mimics requests.Response for Ollama compatibility."""
    def __init__(self, data: Dict[str, Any], status_code: int = 200):
        self._data = data
        self.status_code = status_code
        self.text = json.dumps(data)

    def json(self) -> Dict[str, Any]:
        return self._data


class GeminiBridge:
    def __init__(self, api_key: Optional[str] = None, model: Optional[str] = None):
        self.api_key = api_key or os.getenv("GEMINI_API_KEY", "").strip()
        self.model = model or os.getenv("GEMINI_MODEL", "gemini-3.5-flash-lite").strip()
        self.base_url = "https://generativelanguage.googleapis.com/v1beta/models"

    def is_available(self) -> bool:
        return bool(self.api_key and len(self.api_key) > 10)

    def chat(
        self,
        messages: List[Dict[str, str]],
        temperature: float = 0.7,
        max_tokens: int = 800,
        response_schema: Optional[Dict[str, Any]] = None,
        timeout: int = 15,
    ) -> Dict[str, Any]:
        """
        Converts Ollama/OpenAI messages list to Gemini API format.
        Returns dict matching Ollama chat schema:
        {'message': {'role': 'assistant', 'content': '...'}}
        """
        if not self.is_available():
            raise ValueError("GEMINI_API_KEY not configured or empty")

        system_texts = []
        gemini_contents = []

        for msg in messages:
            role = msg.get("role", "user")
            content = str(msg.get("content", "") or "")
            if not content.strip():
                continue

            if role == "system":
                system_texts.append(content)
            elif role == "assistant":
                gemini_contents.append({"role": "model", "parts": [{"text": content}]})
            else:  # user
                gemini_contents.append({"role": "user", "parts": [{"text": content}]})

        # Ensure at least one user turn
        if not gemini_contents:
            gemini_contents = [{"role": "user", "parts": [{"text": "Hello"}]}]

        payload: Dict[str, Any] = {
            "contents": gemini_contents,
            "generationConfig": {
                "temperature": float(temperature),
                "maxOutputTokens": int(max_tokens),
            }
        }

        if system_texts:
            payload["systemInstruction"] = {
                "parts": [{"text": "\n\n".join(system_texts)}]
            }

        if response_schema:
            payload["generationConfig"]["responseMimeType"] = "application/json"

        url = f"{self.base_url}/{self.model}:generateContent?key={self.api_key}"
        res = requests.post(url, json=payload, timeout=timeout)

        if res.status_code != 200:
            raise RuntimeError(f"Gemini API returned HTTP {res.status_code}: {res.text[:200]}")

        data = res.json()
        candidates = data.get("candidates", [])
        if not candidates:
            raise RuntimeError("Gemini returned empty candidates")

        parts = candidates[0].get("content", {}).get("parts", [])
        if not parts:
            raise RuntimeError("Gemini returned no content parts")

        reply_text = parts[0].get("text", "").strip()
        return {
            "message": {
                "role": "assistant",
                "content": reply_text
            }
        }

    def generate(self, prompt: str, temperature: float = 0.3, max_tokens: int = 1000, timeout: int = 15) -> Dict[str, Any]:
        """
        Generates single prompt text (used by crisis assessment).
        Returns dict matching Ollama generate schema: {'response': '...'}
        """
        res = self.chat(
            messages=[{"role": "user", "content": prompt}],
            temperature=temperature,
            max_tokens=max_tokens,
            timeout=timeout,
        )
        return {"response": res["message"]["content"]}
