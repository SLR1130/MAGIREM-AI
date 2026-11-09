@extends('layouts.app')

@section('title', 'Peer Support & Community - MAGIREM AI')

@section('content')
<div style="margin-bottom: 2rem;">
    <h1 style="color: var(--purple-900); font-size: 2.5rem; font-weight: 700; display: flex; align-items: center; gap: 1rem; margin: 0 0 0.5rem 0;">
        <svg class="icon-lg" style="width: 40px; height: 40px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm0 0h6v-2a6 6 0 00-9-5.656v2.656a4 4 0 013 3.656v2z"></path></svg>
        Peer Support & Community
    </h1>
    <p style="color: var(--purple-600); font-size: 1.1rem; margin: 0;">Connect with peers, share experiences, and grow together</p>
</div>

<!-- Main Features Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    <!-- Discussion Forums -->
    <div class="card" style="border-top: 4px solid #8b5cf6; cursor: pointer; transition: all 0.3s;" onclick="showSection('forums')">
        <h3 style="color: var(--purple-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.5rem;">💬</span> Discussion Forums
        </h3>
        <p style="color: var(--purple-600); margin-bottom: 1rem;">Safe spaces to discuss challenges and share experiences with peers</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <span style="background: var(--purple-100); color: var(--purple-700); padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Academic</span>
            <span style="background: var(--purple-100); color: var(--purple-700); padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Anxiety</span>
            <span style="background: var(--purple-100); color: var(--purple-700); padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Relationships</span>
        </div>
    </div>

    <!-- Group Activities -->
    <div class="card" style="border-top: 4px solid #06b6d4; cursor: pointer; transition: all 0.3s;" onclick="showSection('activities')">
        <h3 style="color: var(--purple-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.5rem;">🎯</span> Group Activities
        </h3>
        <p style="color: var(--purple-600); margin-bottom: 1rem;">Join mindfulness sessions, study circles, and support groups</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <span style="background: #06b6d420; color: #0891b2; padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">🧘 Mindfulness</span>
            <span style="background: #06b6d420; color: #0891b2; padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">📚 Study</span>
            <span style="background: #06b6d420; color: #0891b2; padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">👥 Support</span>
        </div>
    </div>

    <!-- Peer Connections -->
    <div class="card" style="border-top: 4px solid #10b981; cursor: pointer; transition: all 0.3s;" onclick="showSection('connections')">
        <h3 style="color: var(--purple-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.5rem;">🤝</span> Peer Connections
        </h3>
        <p style="color: var(--purple-600); margin-bottom: 1rem;">Connect with peers experiencing similar challenges</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <span style="background: #10b98120; color: #047857; padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Similar Challenges</span>
            <span style="background: #10b98120; color: #047857; padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Study Buddy</span>
        </div>
    </div>
</div>

<!-- Discussion Forums Section -->
<div id="forums-section" style="display: none; margin-bottom: 3rem;">
    <div style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100)); padding: 2rem; border-radius: 1.5rem; margin-bottom: 2rem;">
        <h2 style="color: var(--purple-900); margin: 0 0 1rem 0; font-size: 1.8rem; font-weight: 700;">💬 Discussion Forums</h2>
        <p style="color: var(--purple-600); margin: 0;">Moderated safe spaces where students can discuss challenges and support each other</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
        <!-- Academic Forum -->
        <div class="card" style="border-left: 5px solid #3b82f6;">
            <h3 style="color: var(--purple-900); margin-bottom: 0.5rem;">📚 Academic Stress</h3>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin-bottom: 1rem;">Exam anxiety, workload management, study tips</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>24</strong> active discussions</p>
            </div>
            <a href="#" style="display: inline-block; background: #3b82f6; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">Join Forum</a>
        </div>

        <!-- Anxiety Forum -->
        <div class="card" style="border-left: 5px solid #8b5cf6;">
            <h3 style="color: var(--purple-900); margin-bottom: 0.5rem;">😰 Anxiety & Stress</h3>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin-bottom: 1rem;">Managing anxiety, coping strategies, support</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>18</strong> active discussions</p>
            </div>
            <a href="#" style="display: inline-block; background: #8b5cf6; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">Join Forum</a>
        </div>

        <!-- Relationships Forum -->
        <div class="card" style="border-left: 5px solid #ec4899;">
            <h3 style="color: var(--purple-900); margin-bottom: 0.5rem;">💔 Relationships</h3>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin-bottom: 1rem;">Dating, friendships, family, social skills</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>15</strong> active discussions</p>
            </div>
            <a href="#" style="display: inline-block; background: #ec4899; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">Join Forum</a>
        </div>

        <!-- Mental Health Forum -->
        <div class="card" style="border-left: 5px solid #f59e0b;">
            <h3 style="color: var(--purple-900); margin-bottom: 0.5rem;">🧠 Mental Health</h3>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin-bottom: 1rem;">Depression, self-esteem, personal growth</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>21</strong> active discussions</p>
            </div>
            <a href="#" style="display: inline-block; background: #f59e0b; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">Join Forum</a>
        </div>

        <!-- General Support Forum -->
        <div class="card" style="border-left: 5px solid #10b981;">
            <h3 style="color: var(--purple-900); margin-bottom: 0.5rem;">👥 General Support</h3>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin-bottom: 1rem;">General discussions, advice, encouragement</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>32</strong> active discussions</p>
            </div>
            <a href="#" style="display: inline-block; background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">Join Forum</a>
        </div>
    </div>
</div>

<!-- Group Activities Section -->
<div id="activities-section" style="display: none; margin-bottom: 3rem;">
    <div style="background: linear-gradient(135deg, #06b6d420, #06b6d410); padding: 2rem; border-radius: 1.5rem; margin-bottom: 2rem;">
        <h2 style="color: var(--purple-900); margin: 0 0 1rem 0; font-size: 1.8rem; font-weight: 700;">🎯 Upcoming Group Activities</h2>
        <p style="color: var(--purple-600); margin: 0;">Join mindfulness sessions, study circles, and support groups</p>
    </div>

    <div style="display: grid; gap: 1.5rem;">
        <!-- Mindfulness Session -->
        <div class="card" style="border-left: 5px solid #06b6d4;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="color: var(--purple-900); margin: 0 0 0.5rem 0;">🧘 Guided Mindfulness Session</h3>
                    <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Learn relaxation techniques and meditation</p>
                </div>
                <span style="background: #06b6d420; color: #0891b2; padding: 0.4rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Mindfulness</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: var(--purple-50); border-radius: 0.75rem;">
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">📅 Date</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">Mar 15, 2026</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">🕐 Time</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">3:00 PM - 3:30 PM</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">👥 Participants</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">12/20 registered</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">👨‍🏫 Facilitator</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">Dr. Sarah</p>
                </div>
            </div>
            <button style="background: #06b6d4; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600; transition: all 0.3s;">Register Now</button>
        </div>

        <!-- Study Circle -->
        <div class="card" style="border-left: 5px solid #10b981;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="color: var(--purple-900); margin: 0 0 0.5rem 0;">📚 Study Support Circle</h3>
                    <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Collaborative study group for exam preparation</p>
                </div>
                <span style="background: #10b98120; color: #047857; padding: 0.4rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Study</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: var(--purple-50); border-radius: 0.75rem;">
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">📅 Date</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">Mar 16, 2026</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">🕐 Time</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">5:00 PM - 6:30 PM</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">👥 Participants</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">8/15 registered</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">👨‍🏫 Facilitator</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">Prof. James</p>
                </div>
            </div>
            <button style="background: #10b981; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600; transition: all 0.3s;">Register Now</button>
        </div>

        <!-- Support Group -->
        <div class="card" style="border-left: 5px solid #f59e0b;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="color: var(--purple-900); margin: 0 0 0.5rem 0;">💪 Mental Health Support Group</h3>
                    <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Safe space to share experiences and support each other</p>
                </div>
                <span style="background: #f59e0b20; color: #b45309; padding: 0.4rem 0.8rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600;">Support</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: var(--purple-50); border-radius: 0.75rem;">
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">📅 Date</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">Mar 17, 2026</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">🕐 Time</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">4:00 PM - 5:00 PM</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">👥 Participants</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">10/20 registered</p>
                </div>
                <div>
                    <p style="color: var(--purple-600); font-size: 0.8rem; margin: 0 0 0.3rem 0; font-weight: 600;">👨‍🏫 Facilitator</p>
                    <p style="color: var(--purple-900); margin: 0; font-weight: 600;">Dr. Emma</p>
                </div>
            </div>
            <button style="background: #f59e0b; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600; transition: all 0.3s;">Register Now</button>
        </div>
    </div>
</div>

<!-- Peer Connections Section -->
<div id="connections-section" style="display: none; margin-bottom: 3rem;">
    <div style="background: linear-gradient(135deg, #10b98120, #10b98110); padding: 2rem; border-radius: 1.5rem; margin-bottom: 2rem;">
        <h2 style="color: var(--purple-900); margin: 0 0 1rem 0; font-size: 1.8rem; font-weight: 700;">🤝 Peer Connections</h2>
        <p style="color: var(--purple-600); margin: 0;">Connect with peers experiencing similar challenges and build supportive relationships</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
        <!-- Connection Type 1 -->
        <div class="card" style="border-top: 4px solid #10b981;">
            <h3 style="color: var(--purple-900); margin-bottom: 1rem;">Similar Challenges</h3>
            <p style="color: var(--purple-600); margin-bottom: 1rem; font-size: 0.9rem;">Connect with students facing similar mental health challenges</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>24</strong> peers available</p>
            </div>
            <button style="background: #10b981; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600;">Find Peers</button>
        </div>

        <!-- Connection Type 2 -->
        <div class="card" style="border-top: 4px solid #06b6d4;">
            <h3 style="color: var(--purple-900); margin-bottom: 1rem;">Study Buddy</h3>
            <p style="color: var(--purple-600); margin-bottom: 1rem; font-size: 0.9rem;">Find a study partner for collaborative learning</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>18</strong> peers available</p>
            </div>
            <button style="background: #06b6d4; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600;">Find Buddy</button>
        </div>

        <!-- Connection Type 3 -->
        <div class="card" style="border-top: 4px solid #8b5cf6;">
            <h3 style="color: var(--purple-900); margin-bottom: 1rem;">Support Partner</h3>
            <p style="color: var(--purple-600); margin-bottom: 1rem; font-size: 0.9rem;">Build a one-on-one supportive relationship</p>
            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;"><strong>12</strong> peers available</p>
            </div>
            <button style="background: #8b5cf6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600;">Find Partner</button>
        </div>
    </div>
</div>

<!-- Community Guidelines -->
<div class="card" style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100)); border-radius: 1.5rem; padding: 2.5rem; border: 2px solid var(--purple-200);">
    <h3 style="color: var(--purple-900); margin: 0 0 1.5rem 0; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
        <svg class="icon" style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Community Guidelines
    </h3>
    <ul style="color: var(--purple-700); line-height: 2; margin: 0; padding-left: 1.5rem;">
        <li>✅ Be respectful and supportive to all community members</li>
        <li>✅ Maintain confidentiality and privacy of others</li>
        <li>✅ Share experiences without judgment</li>
        <li>✅ Avoid giving medical or professional advice</li>
        <li>✅ Report inappropriate behavior to moderators</li>
        <li>✅ Use inclusive and non-discriminatory language</li>
        <li>✅ Respect different perspectives and experiences</li>
    </ul>
</div>

<script>
function showSection(section) {
    // Hide all sections
    document.getElementById('forums-section').style.display = 'none';
    document.getElementById('activities-section').style.display = 'none';
    document.getElementById('connections-section').style.display = 'none';
    
    // Show selected section
    if (section === 'forums') {
        document.getElementById('forums-section').style.display = 'block';
    } else if (section === 'activities') {
        document.getElementById('activities-section').style.display = 'block';
    } else if (section === 'connections') {
        document.getElementById('connections-section').style.display = 'block';
    }
}
</script>
@endsection
