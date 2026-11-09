import test from 'node:test';
import assert from 'node:assert/strict';
import { BREATHING_PHASES, breathingPhaseAt, resolvePracticeMode } from '../../resources/js/coping-toolkit-runner.js';

test('existing named coping activities select only their intended runner modes', () => {
    assert.equal(resolvePracticeMode('Box Breathing', 'breathing'), 'breathing');
    assert.equal(resolvePracticeMode('Grounding Technique (5-4-3-2-1)', 'grounding'), 'grounding');
    assert.equal(resolvePracticeMode('Progressive Muscle Relaxation', 'stress_management'), 'muscle');
    assert.equal(resolvePracticeMode('Expressive Journaling', 'stress_management'), 'journal');
    assert.equal(resolvePracticeMode('Cognitive Reframing', 'mindfulness'), 'reframe');
    assert.equal(resolvePracticeMode('Physical Activity', 'stress_management'), 'movement');
});

test('other existing activities preserve their source instructions in the guided fallback mode', () => {
    assert.equal(resolvePracticeMode('Body Scan Meditation', 'mindfulness'), 'guided');
    assert.equal(resolvePracticeMode('Take a Walk', 'stress_management'), 'movement');
    assert.equal(resolvePracticeMode('4-7-8 Breathing', 'breathing'), 'breathing');
});

test('breathing phase clock transitions at boundaries and loops without accumulating intervals', () => {
    assert.equal(breathingPhaseAt(0).phase.kind, 'inhale');
    assert.equal(breathingPhaseAt(3999).remainingSeconds, 1);
    assert.equal(breathingPhaseAt(4000).phase.kind, 'hold');
    assert.equal(breathingPhaseAt(8000).phase.kind, 'exhale');
    assert.equal(breathingPhaseAt(12000).phase.kind, 'rest');
    assert.equal(breathingPhaseAt(16000).phase.kind, 'inhale');
    assert.equal(breathingPhaseAt(-20).phase.kind, 'inhale');
});

test('breathing phase labels are gentle and holds are explicitly optional', () => {
    assert.equal(BREATHING_PHASES[0].label, 'Gentle inhale');
    assert.equal(BREATHING_PHASES[1].label, 'Optional hold');
    assert.equal(BREATHING_PHASES[3].label, 'Optional rest');
});

test('4-7-8 timing can use a separate pattern without changing the shared box-breathing clock', () => {
    const fourSevenEight = [
        { label: 'Gentle inhale', seconds: 4, kind: 'inhale' },
        { label: 'Optional hold', seconds: 7, kind: 'hold' },
        { label: 'Gentle exhale', seconds: 8, kind: 'exhale' },
    ];
    assert.equal(breathingPhaseAt(4000, fourSevenEight).phase.seconds, 7);
    assert.equal(breathingPhaseAt(11000, fourSevenEight).phase.kind, 'exhale');
    assert.equal(BREATHING_PHASES.reduce((sum, phase) => sum + phase.seconds, 0), 16);
});
