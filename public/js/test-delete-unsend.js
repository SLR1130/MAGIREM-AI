/**
 * DELETE/UNSEND FEATURE - RUNTIME TEST HELPERS
 * Paste these in browser console during testing
 */

console.log('🧪 Delete/Unsend Test Helpers Loaded');

// Test 1 & 2: Get visible menu options for a message
function testGetMenuOptions(messageId) {
    console.log(`\n📋 Checking menu options for message ${messageId}`);
    
    // Find message element
    const msg = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!msg) {
        console.error('❌ Message not found');
        return;
    }
    
    // Simulate hover to show menu button
    msg.dispatchEvent(new MouseEvent('mouseenter'));
    
    // Wait for menu button to appear
    setTimeout(() => {
        const menuBtn = msg.querySelector('.message-menu-btn');
        if (menuBtn) {
            menuBtn.click();
            
            // Check visible buttons
            const menu = msg.querySelector('.message-menu');
            if (menu) {
                const buttons = menu.querySelectorAll('button');
                console.log(`✅ Menu opened with ${buttons.length} options:`);
                buttons.forEach(btn => console.log(`  • ${btn.textContent.trim()}`));
            }
        }
    }, 200);
}

// Test 3 & 4: Test unauthorized unsend
async function testUnauthorizedUnsend(messageId, endpoint) {
    console.log(`\n🔒 Testing unauthorized unsend for message ${messageId}`);
    console.log(`   Endpoint: ${endpoint}`);
    
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json'
            }
        });
        
        console.log(`✅ Response Status: ${response.status} ${response.statusText}`);
        const data = await response.json();
        console.log(`   Response Body: ${JSON.stringify(data, null, 2)}`);
        
        return {status: response.status, data: data};
    } catch (error) {
        console.error(`❌ Error: ${error.message}`);
    }
}

// Test 6 & 7: Check physical file existence
async function testFileExists(storagePath) {
    console.log(`\n📁 Checking if file exists: ${storagePath}`);
    
    try {
        const response = await fetch(storagePath, {method: 'HEAD'});
        if (response.ok) {
            console.log(`✅ File EXISTS (200 OK)`);
            return true;
        } else {
            console.log(`❌ File NOT FOUND (${response.status})`);
            return false;
        }
    } catch (error) {
        console.log(`❌ File NOT FOUND (${error.message})`);
        return false;
    }
}

// Test 7: Check unsent message doesn't expose attachment URLs
function testUnsentAttachmentData(messageId) {
    console.log(`\n🔍 Checking attachment data for message ${messageId}`);
    
    // Find message in timeline
    const msg = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!msg) {
        console.error('❌ Message not found');
        return;
    }
    
    // Check if it's a tombstone
    const isTombstone = msg.textContent.includes('unsent') || msg.textContent.includes('Unsent');
    console.log(`   Is Tombstone: ${isTombstone ? '✅ YES' : '❌ NO'}`);
    
    // Check for attachment elements
    const attachmentUrl = msg.querySelector('a[href*="/storage"]');
    const imageElement = msg.querySelector('img[src*="/storage"]');
    
    console.log(`   Attachment URL exposed: ${attachmentUrl ? '❌ YES (BAD)' : '✅ NO (GOOD)'}`);
    console.log(`   Image element exposed: ${imageElement ? '❌ YES (BAD)' : '✅ NO (GOOD)'}`);
}

// Test 10 & 11: Monitor realtime unsend delay
let monitorStartTime = null;

function startMonitoringUnsend() {
    console.log(`\n⏱️ Started monitoring for unsend (will detect within 10 seconds)`);
    monitorStartTime = Date.now();
    
    // Store initial message state
    const messages = document.querySelectorAll('[data-message-id]');
    const initialState = new Map();
    messages.forEach(m => {
        initialState.set(m.getAttribute('data-message-id'), m.textContent);
    });
    
    // Poll for changes
    const interval = setInterval(() => {
        const messages = document.querySelectorAll('[data-message-id]');
        let unseenFound = false;
        
        messages.forEach(m => {
            const msgId = m.getAttribute('data-message-id');
            const initial = initialState.get(msgId);
            const current = m.textContent;
            
            if (initial !== current && current.includes('unsent')) {
                const delay = ((Date.now() - monitorStartTime) / 1000).toFixed(2);
                console.log(`✅ UNSEND DETECTED in ${delay} seconds`);
                console.log(`   Message: "${current.substring(0, 50)}..."`);
                unseenFound = true;
                clearInterval(interval);
            }
        });
        
        if (Date.now() - monitorStartTime > 10000) {
            console.log(`⚠️ No unsend detected within 10 seconds`);
            clearInterval(interval);
        }
    }, 500);
}

// Test 12: Check video call cards don't have menu
function testVideoCallProtection() {
    console.log(`\n📹 Testing video call protection`);
    
    // Find video call cards
    const videoCards = document.querySelectorAll('[data-type="video_call"]');
    console.log(`   Found ${videoCards.length} video call cards`);
    
    if (videoCards.length === 0) {
        console.log('   ℹ️ No video calls in timeline');
        return;
    }
    
    let allProtected = true;
    videoCards.forEach((card, idx) => {
        const hasMenu = card.querySelector('.message-menu-btn');
        const status = hasMenu ? '❌ HAS MENU (BAD)' : '✅ NO MENU (GOOD)';
        console.log(`   Call ${idx + 1}: ${status}`);
        if (hasMenu) allProtected = false;
    });
    
    console.log(`   Overall: ${allProtected ? '✅ PASS' : '❌ FAIL'}`);
}

// Test 13: Check pending message behavior
function testPendingMessageBehavior() {
    console.log(`\n⏳ Testing pending message behavior`);
    
    // Find sending messages
    const sendingMsgs = document.querySelectorAll('[data-state="sending"]');
    console.log(`   Found ${sendingMsgs.length} sending messages`);
    
    sendingMsgs.forEach((msg, idx) => {
        const hasMenu = msg.querySelector('.message-menu-btn');
        const status = hasMenu ? '❌ HAS MENU (BAD)' : '✅ NO MENU (GOOD)';
        console.log(`   Message ${idx + 1}: ${status}`);
    });
}

// Display summary
function testSummary() {
    console.log(`\n${'='.repeat(60)}`);
    console.log('📊 TEST SUMMARY');
    console.log(`${'='.repeat(60)}`);
    console.log(`
Available test functions:
  • testGetMenuOptions(messageId) - Check menu options
  • testUnauthorizedUnsend(messageId, endpoint) - Test 403
  • testFileExists(storagePath) - Check physical file
  • testUnsentAttachmentData(messageId) - Check attachment cleared
  • startMonitoringUnsend() - Monitor realtime unsend
  • testVideoCallProtection() - Check video calls protected
  • testPendingMessageBehavior() - Check pending messages
  • testSummary() - Show this help

Example usage:
  testGetMenuOptions(123);
  testUnauthorizedUnsend(123, '/counselor/api/messages/123/unsend');
  testFileExists('/storage/attachments/xyz.jpg');
`);
}

// Auto-show help
testSummary();
