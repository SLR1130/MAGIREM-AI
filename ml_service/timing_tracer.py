#!/usr/bin/env python3
"""
Timing Tracer - Adds detailed timing logging to ML API
Helps identify which functions are causing latency

Add this to unified_ml_api_with_model.py at the start of the chat() function
"""

import time
from contextlib import contextmanager
from datetime import datetime
import json

class TimingTracer:
    """Track timing of different components"""
    
    def __init__(self):
        self.timings = {}
        self.start_time = time.time()
    
    @contextmanager
    def track(self, component_name: str):
        """Context manager to track timing of a component"""
        start = time.time()
        try:
            yield
        finally:
            elapsed = time.time() - start
            self.timings[component_name] = elapsed
            print(f"[TIMING] {component_name}: {elapsed:.3f}s")
    
    def report(self):
        """Print timing report"""
        total = time.time() - self.start_time
        print("\n" + "=" * 70)
        print("TIMING REPORT")
        print("=" * 70)
        
        sorted_timings = sorted(self.timings.items(), key=lambda x: x[1], reverse=True)
        
        for component, elapsed in sorted_timings:
            percentage = (elapsed / total) * 100 if total > 0 else 0
            bar = "█" * int(percentage / 5)
            print(f"{component:.<40} {elapsed:>6.3f}s ({percentage:>5.1f}%) {bar}")
        
        print("-" * 70)
        print(f"{'TOTAL':.<40} {total:>6.3f}s (100.0%)")
        print("=" * 70 + "\n")
        
        return {
            'total_time': total,
            'components': dict(sorted_timings)
        }

# Test the tracer
if __name__ == "__main__":
    tracer = TimingTracer()
    
    # Simulate different components
    with tracer.track("semantic_analysis"):
        time.sleep(1.5)
    
    with tracer.track("memory_retrieval"):
        time.sleep(0.8)
    
    with tracer.track("ollama_understanding"):
        time.sleep(2.0)
    
    with tracer.track("response_generation"):
        time.sleep(15.0)
    
    with tracer.track("database_storage"):
        time.sleep(0.3)
    
    report = tracer.report()
    print(f"Report data: {json.dumps(report, indent=2)}")
