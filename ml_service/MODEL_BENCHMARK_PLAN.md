# Ollama Model Benchmark Plan

## Current Situation
- **Current Model:** neural-chat:latest (7B, Q4_0)
- **Current Speed:** 4.4 tokens/second
- **Current Issue:** Two sequential Ollama calls timeout at 15s and 25s
- **Problem:** Model inference takes 15-20+ seconds per call

## Goal
Find a faster model that can:
1. Generate responses in <10 seconds
2. Support multilingual input (English, Tagalog, Taglish)
3. Produce coherent emotional/contextual responses
4. Work within current hardware constraints

## Candidate Models for Benchmarking

### Tier 1: Very Fast Small Models
Models optimized for speed, suitable for CPU/limited GPU:

**1. Phi 2.7B (3.3 GB)**
- Parameter size: 2.7B (vs 7B current)
- Quantization: Q4_0
- Expected speed: 2x faster than neural-chat (8-10 tokens/sec)
- Pros: Significantly faster, Microsoft research quality
- Cons: Smaller model, may have reduced reasoning
- Language support: English only (primarily)
- Ollama repo: `phi:latest` or `phi:2.7b`

**2. Orca 3B (3.5 GB)**
- Parameter size: 3B
- Quantization: Q4_0
- Expected speed: 1.5-2x faster
- Pros: Instruction-following, good quality
- Cons: Limited language support
- Ollama repo: `orca-mini:3b`

**3. OpenHermes 2.5 - 3B (1.6 GB - very small)**
- Parameter size: 3B
- Expected speed: Very fast (10+ tokens/sec)
- Pros: Extremely compact, very fast
- Cons: Limited reasoning, English-focused
- Ollama repo: `openhermes:3b` (if available)

### Tier 2: Balanced Models
Models balancing speed and quality:

**4. Mistral 7B (4.1 GB)**
- Parameter size: 7B (same as neural-chat)
- Quantization: Q4_0
- Expected speed: Similar to neural-chat (but different architecture)
- Pros: Stronger reasoning than neural-chat, better instruction following
- Cons: Similar size/speed, not necessarily faster
- Language support: Multilingual support better than neural-chat
- Ollama repo: `mistral:latest`

**5. Neural Chat 7B vs Variants**
- Current model is already neural-chat
- Other quantizations available: Q5_0 (slower but better quality)
- Not faster

**6. Zephyr 7B (4.2 GB)**
- Parameter size: 7B
- Based on Mistral, fine-tuned for dialogue
- Expected speed: Similar to neural-chat
- Pros: Good dialogue quality
- Cons: Similar performance to current
- Ollama repo: `zephyr:7b`

### Tier 3: Multilingual-Optimized Models
Better support for Tagalog/Taglish:

**7. Aya Multilingual 8B (4.7 GB)**
- Parameter size: 8B
- Language support: 101+ languages including Tagalog
- Expected speed: Similar to neural-chat (4-5 tokens/sec)
- Pros: Excellent multilingual support
- Cons: Not faster, larger
- Ollama repo: May need manual setup (not in standard Ollama)

**8. BLOOM 7B (14 GB - too large)**
- Excellent multilingual but too large for current setup
- Skip this

### Tier 4: Specialized Models
Purpose-built for specific tasks:

**9. TinyLlama 1.1B (637 MB - extremely fast)**
- Parameter size: 1.1B
- Expected speed: 15-20 tokens/sec (very fast)
- Pros: Ultra-fast, minimal resource usage
- Cons: Limited reasoning, English only
- Good for testing speed limits
- Ollama repo: `tinyllama:1.1b`

## Hardware Considerations

Current machine (from Ollama diagnostics):
- Running neural-chat:latest (7B, Q4_0, 3.83 GB)
- CPU baseline for inference
- 100% CPU utilization when running model
- No explicit GPU mentioned (likely CPU-only)

**Model Size Limits:**
- <2 GB: Very comfortable even on CPU
- 2-4 GB: Safe for current hardware
- 4-6 GB: Possible but may strain memory
- >6 GB: Risky, may cause swapping

## Recommended Benchmark Candidates

Based on practical constraints and goals:

### Priority 1: Speed-Focused (Most Likely to Solve Timeout)
1. **Phi 2.7B** (3.3 GB)
   - Reason: Significantly smaller, designed for speed
   - Potential: 50% speed improvement
   
2. **TinyLlama 1.1B** (637 MB)
   - Reason: Test extreme speed baseline
   - Potential: 4x speed improvement
   - Caveat: Quality may be poor

3. **Mistral 7B** (4.1 GB)
   - Reason: Better multilingual support, same size
   - Potential: 10-20% speed improvement (architecture-dependent)

### Priority 2: Multilingual-Focused (Better Language Support)
4. **Orca-mini 3B** (3.5 GB)
   - Reason: Smaller, faster, instruction-following
   - Trade-off: Less multilingual support

## Test Protocol

For each candidate model:
1. Query the model via Ollama API (not CLI)
2. Use exact same test prompts for all candidates
3. Measure:
   - Response time (wall clock)
   - Response quality (coherence, format, accuracy)
   - Token generation speed (from Ollama response metadata)
   - Multilingual understanding
   - Sarcasm/context detection

### Test Prompts (10 diverse scenarios)

**English - Positive:**
```
Message: "My friends surprised me today and it made my whole day."
Task: Understand the emotional context and respond naturally
```

**English - Negative:**
```
Message: "I studied for weeks and still failed my exam."
Task: Show understanding of disappointment
```

**English - Anxiety:**
```
Message: "I have three projects due tomorrow and I haven't finished any of them."
Task: Recognize stress and offer support
```

**English - Sarcasm:**
```
Message: "My professor just gave us three deadlines for tomorrow. What a wonderful surprise 😂"
Task: Detect sarcasm and respond appropriately
```

**Relationship Issue:**
```
Message: "Yeah about that, my friend said that my girlfriend is cheating on me."
Task: Show empathy for relationship concern
```

**Tagalog:**
```
Message: "Grabe naman, ang saya talaga. Tatlong deadline bukas 😂"
Translation: "Wow, so much joy. Three deadlines tomorrow 😂" (sarcastic)
Task: Understand Tagalog sarcasm
```

**Taglish:**
```
Message: "Bro sobrang stressed ako, three deadlines tomorrow tapos wala pa akong natatapos."
Translation: "Bro I'm so stressed, three deadlines tomorrow and I haven't finished anything."
Task: Understand mixed-language anxiety
```

**Direct Question:**
```
Message: "How should I prepare for my exam tomorrow?"
Task: Provide useful advice
```

## Evaluation Criteria

For each model, record:

| Criterion | How to Measure |
|---|---|
| **Response Time** | Time from request to complete response |
| **Token Speed** | tokens/second from Ollama metadata |
| **Format Adherence** | Does response follow expected format? |
| **English Understanding** | Coherence and accuracy in English |
| **Sarcasm Detection** | Does it recognize sarcasm? |
| **Multilingual Support** | Tagalog/Taglish understanding |
| **Emotional Awareness** | Does it match emotional context? |
| **Response Quality** | Is the response helpful/natural? |
| **Usefulness Score** | 1-10: Would this work for the app? |

## What This Is NOT

- ✗ Not installing models yet (research phase only)
- ✗ Not modifying application code
- ✗ Not deleting or changing current setup
- ✗ Not committing to any particular model
- ✗ Not benchmark testing yet (just planning)

## What This IS

- ✓ Planning which models to test
- ✓ Understanding model characteristics
- ✓ Identifying practical candidates based on hardware
- ✓ Preparing test structure and criteria
- ✓ Determining feasibility before commitment

## Next Steps

1. Review and approve candidate model list
2. Identify which 3-5 models to actually test
3. Create benchmark test script
4. Run controlled tests using same prompts
5. Measure and compare results
6. Recommend best model based on data
7. Only then: decide whether to switch

## Important Notes

- **No commitment yet:** Just because we test a model doesn't mean we use it
- **Current model stays:** neural-chat stays running until replacement is proven faster
- **Reversible:** Can always switch back to neural-chat
- **Data-driven:** Decision will be based on actual measurements, not guessing
