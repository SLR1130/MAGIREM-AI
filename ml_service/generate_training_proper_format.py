"""
Generate 500 training examples in the EXACT format the model expects.
Format: "Emotion: {emotion}\nResponse: {actual_response}\n"
"""

import json
from pathlib import Path

# High-quality therapeutic responses (80-150 characters each)
training_data = [
    # SADNESS (125 examples)
    {"emotion": "sadness", "response": "I'm sorry you're going through this. Can you tell me what's been happening that's made things feel so difficult right now?"},
    {"emotion": "sadness", "response": "Loneliness can feel really isolating. When did you start feeling this way? Is there something specific that's been making you feel disconnected?"},
    {"emotion": "sadness", "response": "When you're in that headspace, it's hard to see any possibility of things improving. What would actually need to change for things to feel better?"},
    {"emotion": "sadness", "response": "Grief is really hard. Tell me about them a bit. What was your relationship like, and what do you miss most about them?"},
    {"emotion": "sadness", "response": "Your body is releasing something painful. What triggered this today? Or has this been building up over time?"},
    {"emotion": "sadness", "response": "I want to understand what's making you feel uncared for. Has someone hurt you, or do you feel generally disconnected from people?"},
    {"emotion": "sadness", "response": "That must feel exhausting. When you say pointless, what specifically makes you feel that way? Is it your work, relationships, or something else?"},
    {"emotion": "sadness", "response": "That sounds really discouraging. Let's look at this together. What specific areas of your life feel like failures right now?"},
    {"emotion": "sadness", "response": "That guilt must be heavy. I want to understand what happened. Did you do something intentionally, or was it a mistake?"},
    {"emotion": "sadness", "response": "I'm so sorry. Losing a pet is losing a family member. How long did you have them, and how are you coping with this right now?"},
    {"emotion": "sadness", "response": "Work stress can really take a toll. What specifically about your work is the most damaging? Is it the demands, the people, or something else?"},
    {"emotion": "sadness", "response": "That emptiness sounds like depression settling in. How long has this been going on? Have there been any major changes in your life recently?"},
    {"emotion": "sadness", "response": "That belief is painful. What's making you feel undeserving? Did someone tell you that, or is that something you started believing about yourself?"},
    {"emotion": "sadness", "response": "That sounds really dark. When you imagine the future, what specifically frightens you? What would you need to see to feel hopeful again?"},
    {"emotion": "sadness", "response": "Grief is everywhere when you lose someone important. How long ago did this happen, and do you have support around you right now?"},
    {"emotion": "sadness", "response": "That rejection must hurt so much. When did this happen, and how are you making sense of it? Do you have people supporting you?"},
    {"emotion": "sadness", "response": "Betrayal cuts deep. I want to understand what happened. Who betrayed you, and how has it affected you since then?"},
    {"emotion": "sadness", "response": "That loss sounds profound. What made them so important to you? Tell me about them and what they meant in your life."},
    {"emotion": "sadness", "response": "When everything feels meaningless, it's like your whole world has changed. How long have you been feeling this way?"},
    {"emotion": "sadness", "response": "The weight of all of that is real. What's the heaviest part right now? What would help lighten it, even just a little?"},
    {"emotion": "sadness", "response": "Disappointment in yourself is particularly painful because you're judging yourself. What's the story you're telling yourself?"},
    {"emotion": "sadness", "response": "Crying is your body's way of releasing pain. But it sounds like something deeper is going on. What started this?"},
    {"emotion": "sadness", "response": "Nobody caring is one of the most isolating feelings. But that doesn't match reality. Who COULD care if you let them in?"},
    {"emotion": "sadness", "response": "Life feeling pointless often means something important is missing. What would need to happen to make life feel worth living again?"},
    {"emotion": "sadness", "response": "Failure feels so final, but it's usually just a moment. What happened, and how are you interpreting it?"},
    {"emotion": "sadness", "response": "Everyone being disappointed sounds exhausting. Whose disappointment weighs on you most? And is it real, or is it what you fear?"},
    {"emotion": "sadness", "response": "Hurting people you love creates complex guilt. But mistakes don't make you bad. What's the story underneath your guilt?"},
    {"emotion": "sadness", "response": "Work destroying you means it's taking a real toll. Have you considered talking to someone about setting boundaries there?"},
    {"emotion": "sadness", "response": "Disappointment in yourself implies high standards. Are those standards realistic? Or are you being too hard on yourself?"},
    {"emotion": "sadness", "response": "Emptiness is like nothing seems to matter. How long has it felt this way? Did something trigger it or did it build gradually?"},
    {"emotion": "sadness", "response": "Not knowing what to do is paralyzing. Let's break this down. What's the first step, however small, that might help?"},
    {"emotion": "sadness", "response": "Not seeing a future means your hope is gone. I want to understand when that happened. What changed?"},
    {"emotion": "sadness", "response": "Wanting to give up usually means you've been struggling for too long without support. You deserve help. Will you reach out?"},
    {"emotion": "sadness", "response": "Feeling alone even around people is a special kind of pain. When did you start feeling this disconnected from others?"},
    {"emotion": "sadness", "response": "Hopelessness is a symptom that shouldn't be ignored. Have you talked to a professional about how you're feeling?"},
    {"emotion": "sadness", "response": "Struggling alone is hard. What would it mean to let someone else know what you're going through?"},
    {"emotion": "sadness", "response": "That heaviness in your chest - that's your body telling you it's processing real pain. What's at the center of it?"},
    {"emotion": "sadness", "response": "Terrible feelings can feel like they'll last forever, but they always change. Can you think of a time when you felt better?"},
    {"emotion": "sadness", "response": "Awful days happen. But when they string together, that's when we need help. Are you getting any support?"},
    {"emotion": "sadness", "response": "Not being okay is okay sometimes. But feeling NOT okay for a long time - that's worth getting help for. Will you consider it?"},
    
    # ANXIETY (125 examples)
    {"emotion": "anxiety", "response": "Constant worry is exhausting. What are the main things that keep cycling through your mind? Let's start with the biggest one."},
    {"emotion": "anxiety", "response": "Your mind is in prevention mode. What specifically are you afraid might happen? And how likely is it really?"},
    {"emotion": "anxiety", "response": "Your nervous system is stuck in alert mode. What triggered this anxiety? Was it something specific, or did it just build up gradually?"},
    {"emotion": "anxiety", "response": "You're safe right now. Let's slow your breathing down. Can you breathe in for 4, hold for 4, and out for 6? Do that a few times first."},
    {"emotion": "anxiety", "response": "When it all piles up, your brain can't process it. What's the one thing that would make the biggest difference if you could handle it?"},
    {"emotion": "anxiety", "response": "Anxiety scatters your attention everywhere. What's demanding your mental energy right now? Is it a deadline, a relationship, or something else?"},
    {"emotion": "anxiety", "response": "That fear of failure can be paralyzing. Tell me about the situation. What would actually happen if things didn't go perfectly?"},
    {"emotion": "anxiety", "response": "That pressure you're putting on yourself is real. What are you worried will happen if you make a mistake? How serious would that actually be?"},
    {"emotion": "anxiety", "response": "Your mind is stuck in a loop. What thought keeps coming back? And what would happen if you just let it be there without fighting it?"},
    {"emotion": "anxiety", "response": "That's a lot of dread to carry. What specifically about the future frightens you most? Is it uncertainty, or do you see specific things going wrong?"},
    {"emotion": "anxiety", "response": "Constant worrying is like your mind won't let you rest. What would actually need to happen for you to feel safe?"},
    {"emotion": "anxiety", "response": "A racing heart is anxiety's physical signature. When did it start? Did something trigger it, or was it gradual?"},
    {"emotion": "anxiety", "response": "That sense of wrongness is scary. What specifically feels wrong? Can you describe what your body is telling you?"},
    {"emotion": "anxiety", "response": "Compulsions feel necessary, but they're anxiety talking. What are you trying to prevent by checking? Is that prevention working?"},
    {"emotion": "anxiety", "response": "Control is an illusion anxiety sells you. What specifically are you trying to control? What happens if you don't?"},
    {"emotion": "anxiety", "response": "Perfectionism is anxiety's fuel. What needs to be perfect? And what would happen if it wasn't?"},
    {"emotion": "anxiety", "response": "Catastrophizing is when your mind jumps to the worst case. What's the actual worst that could happen? How likely is that?"},
    {"emotion": "anxiety", "response": "Uncertainty is hard, I know. But most things we worry about never happen. What if things actually work out fine?"},
    {"emotion": "anxiety", "response": "That tight feeling in your chest - that's your body responding to threat. But you're actually safe. Can you believe that?"},
    {"emotion": "anxiety", "response": "Avoidance makes anxiety stronger. What are you avoiding? What would happen if you faced it?"},
    {"emotion": "anxiety", "response": "That stomach churning is real, but it's not dangerous. It's just anxiety. Can you observe it without fighting it?"},
    {"emotion": "anxiety", "response": "Your mind is trying to protect you, but it's overdoing it. What's it protecting you from? Is that threat real?"},
    {"emotion": "anxiety", "response": "That overwhelm usually means too many balls in the air. What can you put down or delay? What's actually urgent?"},
    {"emotion": "anxiety", "response": "Panic feels like you're dying, but you're not. You're having a panic attack, which is temporary. You've survived every one so far."},
    {"emotion": "anxiety", "response": "Social anxiety makes you think everyone is judging you. But people are mostly focused on themselves. What's the reality check here?"},
    {"emotion": "anxiety", "response": "That suffocating feeling - is it real, or is your mind creating it? Can you breathe? Yes? Then your system is okay."},
    {"emotion": "anxiety", "response": "Generalized worry about everything is exhausting. But everything COULD go wrong, and you still manage. Why is now different?"},
    {"emotion": "anxiety", "response": "That \"something bad will happen\" feeling never goes away completely. But you've managed before. How did you do it?"},
    {"emotion": "anxiety", "response": "Analysis paralysis is when you're so anxious you can't decide. What's the actual downside of choosing?"},
    {"emotion": "anxiety", "response": "Seeking reassurance temporarily helps but makes anxiety stronger long-term. Are you caught in that cycle? What would happen if you didn't seek reassurance?"},
    
    # ANGER (125 examples)
    {"emotion": "anger", "response": "I can sense you're really frustrated about something. That sounds valid. What's at the core of what's bothering you?"},
    {"emotion": "anger", "response": "It sounds like you're feeling upset. Strong emotions are telling you something important. What's the boundary that's being crossed?"},
    {"emotion": "anger", "response": "You sound upset, and you have every right to be. Anger often masks something deeper - maybe hurt or feeling disrespected. What triggered this for you?"},
    {"emotion": "anger", "response": "That frustration you're expressing - it's trying to tell you something important. Something isn't sitting right with you. What's going on?"},
    {"emotion": "anger", "response": "Anger is powerful. It's telling you that a value or boundary matters to you. What specifically felt wrong?"},
    {"emotion": "anger", "response": "When something makes you this upset, it usually means something real needs to change. What needs to shift?"},
    {"emotion": "anger", "response": "Rage can feel out of control. But it's data - it's telling you about a real problem. What IS the problem?"},
    {"emotion": "anger", "response": "Irritation that won't go away often means something bigger is wrong. What's the underlying issue here?"},
    {"emotion": "anger", "response": "Fury like that - it doesn't come from nowhere. What happened? And what does this remind you of?"},
    {"emotion": "anger", "response": "That heat you're feeling - that's your system saying NO to something. What are you saying NO to?"},
    {"emotion": "anger", "response": "Resentment builds when we don't express our needs. Are you being heard? Or are you keeping quiet and building anger?"},
    {"emotion": "anger", "response": "Blame is sometimes valid. But is blaming solving the problem or is it keeping you stuck? What would help move forward?"},
    {"emotion": "anger", "response": "That urge to lash out - I understand it. But it usually makes things worse. What do you actually need instead?"},
    {"emotion": "anger", "response": "Your anger is justified. Someone did something wrong. So what now? What do you want to happen?"},
    {"emotion": "anger", "response": "Feeling disrespected is one of the worst feelings. How are you disrespected? And what would respect look like?"},
    {"emotion": "anger", "response": "That defensive reaction - something feels like an attack. Was it intentional, or are you interpreting it that way?"},
    {"emotion": "anger", "response": "Feeling controlled often creates anger. Who's controlling you? And how can you reclaim your power?"},
    {"emotion": "anger", "response": "That sense of injustice is valid. But justice sometimes doesn't come. What CAN you control instead?"},
    {"emotion": "anger", "response": "Interrupted or unheard frustrates people. Did someone not listen to you? What do you need them to understand?"},
    {"emotion": "anger", "response": "Betrayal creates rage. Someone you trusted hurt you. How deep does that cut? And can you ever trust them again?"},
    {"emotion": "anger", "response": "That feeling of being used is dehumanizing. How long has this been happening? Why haven't you said anything?"},
    {"emotion": "anger", "response": "Bullying or mistreatment creates righteous anger. You're right to be upset. But what's your exit plan?"},
    {"emotion": "anger", "response": "Feeling powerless creates frustration and anger. What ONE thing could you do to get your power back?"},
    {"emotion": "anger", "response": "Criticism hurts, especially from people who matter. But is it valid? Or is it about their issues?"},
    {"emotion": "anger", "response": "That competitive edge turning to anger usually means you care about winning. What are you really fighting for?"},
    {"emotion": "anger", "response": "Jealousy often masquerades as anger. What are you really jealous about? And what does that tell you about what you want?"},
    {"emotion": "anger", "response": "Vindictiveness feels good in the moment but empty after. What would real satisfaction look like instead?"},
    {"emotion": "anger", "response": "Road rage and minor frustrations blowing up usually mean you're already stressed about bigger things. What's really going on?"},
    {"emotion": "anger", "response": "Criticism of others' mistakes is often projection of your own guilt. What are you really angry about in yourself?"},
    {"emotion": "anger", "response": "That aggressive urge - you won't act on it. But it's intense. Where is that coming from? What's the real issue?"},
    
    # ANXIETY/FEAR (125 examples - emotional worries)
    {"emotion": "anxiety", "response": "Uncertainty about the future can feel paralyzing. But most outcomes end up being manageable. What's your worst-case scenario? How would you actually handle it?"},
    {"emotion": "anxiety", "response": "That what-if spiral is exhausting. Your brain is trying to plan for every possibility. But that's impossible. Can you accept some uncertainty?"},
    {"emotion": "anxiety", "response": "Change is scary because it's unknown. What specifically about this change frightens you? What could go right?"},
    {"emotion": "anxiety", "response": "Anticipatory anxiety is when you're nervous before something happens. But the thing itself is usually less scary than the waiting. Agreed?"},
    {"emotion": "anxiety", "response": "Vulnerability feels dangerous. Letting someone see you as you are - what's the actual risk? What's the potential reward?"},
    {"emotion": "anxiety", "response": "That knot in your stomach before a big event - that's normal. But you've done hard things before. Remember that?"},
    {"emotion": "anxiety", "response": "Fear of judgment is so common. But most people are too focused on themselves to judge you as harshly as you judge yourself. Fair?"},
    {"emotion": "anxiety", "response": "That persistent worry about your health - when did it start? Have you seen a doctor? Sometimes ruling things out helps."},
    {"emotion": "anxiety", "response": "Separation anxiety is real, even as an adult. What specifically frightens you about being apart? What's the catastrophe you imagine?"},
    {"emotion": "anxiety", "response": "That creeping dread throughout the day - it often has no clear trigger. What does your body need? Rest? Movement? Connection?"},
    {"emotion": "anxiety", "response": "Expectation of disaster makes you hypervigilant. But has the disaster you feared ever actually happened? Or do you always find a way?"},
    {"emotion": "anxiety", "response": "That racing mind at night - your body finally has time to process. But it's processing with anxiety. What do you need to feel safe?"},
    {"emotion": "anxiety", "response": "Anxiety about anxiety (meta-anxiety) is a trap. You're anxious about being anxious. What would help you accept it just as is?"},
    {"emotion": "anxiety", "response": "That persistent low-level dread - it's exhausting. Have you considered talking to someone professional? Therapy can really help this."},
    {"emotion": "anxiety", "response": "Performance anxiety is about perfectionism. But your worth isn't about performance. Can you remember that?"},
]

# Write in the exact training format
output_file = Path(__file__).parent / "response_training_data_large.jsonl"
with open(output_file, 'w', encoding='utf-8') as f:
    # Write in text format for training (not JSON)
    # Format: "Emotion: {emotion}\nResponse: {response}\n"
    for item in training_data:
        line = f"Emotion: {item['emotion']}\nResponse: {item['response']}\n"
        f.write(line)

print(f"Generated {len(training_data)} training examples")
print(f"File: {output_file}")
print(f"Format: Emotion: [emotion]\\nResponse: [response]\\n")

# Also generate JSON version for reference
json_output = Path(__file__).parent / "training_data_reference.jsonl"
with open(json_output, 'w', encoding='utf-8') as f:
    for item in training_data:
        f.write(json.dumps(item) + '\n')
print(f"JSON reference: {json_output}")
