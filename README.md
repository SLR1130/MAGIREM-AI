# 🌿 MAGIREM AI: An AI-Supported Student Emotional Wellness and Support Platform

<div align="center">

![Institution](https://img.shields.io/badge/Institution-Mindoro%20State%20University-103f32?style=for-the-badge)
![SDG](https://img.shields.io/badge/UN%20SDG-Goal%203%3A%20Good%20Health%20%26%20Well--being-236b55?style=for-the-badge)
![Laravel](https://img.shields.io/badge/Laravel-12-red?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?style=for-the-badge&logo=php)
![Python](https://img.shields.io/badge/Python-3.10%2B-blue?style=for-the-badge&logo=python)
![Vite](https://img.shields.io/badge/Vite-7.3-646cff?style=for-the-badge&logo=vite)
![PWA](https://img.shields.io/badge/PWA-Installable-gold?style=for-the-badge)

**A Guiding Light for Every Emotion · Mindoro State University (MinSU)**

[Quick Start](#-quick-start) • [Core Modules](#-core-modules) • [Architecture](#-system-architecture) • [Design System](#-design-system) • [AI & ML Stack](#-aiml-architecture) • [Security](#-security--privacy)

</div>

---

## 📖 About MAGIREM AI

**MAGIREM AI** is an institutional student mental health and emotional wellness platform engineered specifically for **Mindoro State University (MinSU)** students, guidance counselors, and administrators. 

University life can often feel overwhelming. MAGIREM AI bridges the gap between self-guided reflection and professional guidance by pairing **MAGI**—an empathetic, multilingual conversational AI companion—with direct, confidential counseling workflows, grounding coping toolkits, and peer community support.

### 🎯 Alignment with UN Sustainable Development Goals
- **UN SDG 3: Good Health and Well-being** — Proactively destigmatizing mental health support, detecting early emotional distress, and facilitating timely access to university guidance professionals.
- **Institutional Care** — Providing a secure, confidential environment tailored to Mindoro State University student life.

---

## ✨ Core Modules

### 🤖 1. MAGI AI Companion (v2.0)
- **Empathetic Conversational Agent**: Context-aware, non-judgmental conversational space powered by conversational NLP models.
- **Multilingual Understanding**: Comprehends English, Tagalog, and natural student Taglish expressions.
- **Emotion Classification**: Integrated transformer-based emotion detection classifying complex emotional states (joy, sadness, anxiety, anger, overwhelm, exhaustion, neutral).
- **Proactive Crisis Routing**: Immediate, non-invasive detection of high-distress phrases with instant referral cards, crisis hotline triggers, and counselor intervention pathways.

### 📝 2. Emotional Journaling & Mood Tracking
- **Private Journal**: Expressive writing workspace with AI sentiment tagging and mood tracking.
- **Reflection Prompts**: Contextual prompts that guide students through self-reflection and emotional processing.
- **Historical Trajectory**: Visual mood timeline empowering students to observe emotional trends over weeks and months.

### 🧘 3. Coping Toolkit & Grounding Exercises
- **Guided Box Breathing**: Interactive animated breathing pacer with visual expansion/contraction cues.
- **5-4-3-2-1 Sensory Grounding**: Step-by-step grounding tool for de-escalating acute panic and anxiety.
- **Mindfulness & Calming Audios**: Ambient soundscapes and guided audio meditations.
- **Categorized Self-Help Resources**: Evidence-based strategies for academic stress, grief, sleep hygiene, and social anxiety.

### 👥 4. Community Circle (Peer Support)
- **Moderated Peer Space**: Students share reflections, wellness milestones, and supportive messages.
- **Interactive Engagement**: Uplifting reactions, thread replies, and community empathy.
- **Automated Safety Filters**: Toxic language, harassment, and crisis intent filters with one-click report escalation to guidance staff.

### 🩺 5. Counselor Workspace & Tele-Counseling
- **Real-Time Student Triage**: Counselor dashboard prioritizing students flagged with high-risk or attention-needed indicators.
- **Confidential Chat**: Secure, real-time counselor-student messaging with private authenticated attachment streaming (supporting notes, medical certs, and documents).
- **Appointment Scheduling**: Automated booking, schedule conflict resolution, and confirmation management.
- **Integrated Video Consultations**: Browser-based video calling overlay for remote tele-counseling sessions.
- **Clinical Session Notes**: Structured documentation and intervention tracking strictly confidential to licensed guidance staff.

### 🛡️ 6. Admin Command Center
- **User & Account Verification**: Multi-stage verification workflow for student ID validation and institutional `@minsu.edu.ph` domains.
- **Campus Emotional Climate**: Anonymized macro-level mental health analytics, trend curves, and risk distribution reports.
- **Moderation Queue**: Rapid review of reported community posts and user moderation actions.
- **System Governance**: Role-Based Access Control (RBAC), audit logging, and automated last-admin protection safeguards.

---

## 📱 Modern UI & Navigation Design

MAGIREM AI features a modern, floating glassmorphic design language crafted for seamless usability across both laptops and mobile devices:

- **Desktop / Laptop Floating Island**:
  - Rounded pill navbar (`border-radius: 9999px`) with frosted glass blur (`backdrop-filter: blur(20px)`), subtle ambient drop shadows, and elevation transitions on scroll.
  - Interactive **Icon + Name** pairs for every destination (`Home`, `AI Companion`, `Journal`, `Appointments`, `Community`, `Explore`).
  - **Glowing Hover Highlights**: Dynamic scaling, icon badge illumination (`#103f32` forest background with `#f4c84e` gold icon glow), and smooth cubic-bezier transitions.
- **Mobile Experience ("Real Mobile App Full Icons")**:
  - Dedicated **Floating Mobile Bottom App Dock** (`.student-mobile-dock`) with 6 full touch-friendly icons (`Home`, `MAGI AI`, `Journal`, `Appts`, `Circle`, `Explore`).
  - Native-style **Slide-up Explore Sheet** with dedicated cards for *Insights*, *Coping Toolkit*, and *Wellness Resources*.
  - Automatic content clearance padding preventing bottom dock obstruction.
- **PWA Ready**: Offline caching, service worker integration, and installable as a standalone app on Android, iOS, and desktop.

---

## 🎨 Design System & Color Tokens

The visual identity honors the natural botanical heritage and campus atmosphere of Mindoro State University:

```css
--forest:       #103F32;  /* Primary brand & headers */
--emerald:      #236B55;  /* Active accents & primary buttons */
--emerald-soft: #2F7C62;  /* Secondary interactive elements */
--gold:         #F4C84E;  /* Illumination, badges & active glows */
--mint-soft:    #E6F1EB;  /* Tinted card backgrounds & hover states */
--ivory:        #F8FAF7;  /* Canvas background */
--ink:          #263A33;  /* High-contrast body typography */
--muted:        #708078;  /* Supporting captions & metadata */
```

---

## 🔧 Technology Stack

| Layer | Technologies |
|---|---|
| **Backend Framework** | Laravel 12 (PHP 8.2+) |
| **Frontend Architecture** | Blade Templates, Custom Design Tokens, Vanilla JS + React Components |
| **Asset Pipeline** | Vite 7.3, PostCSS |
| **Database** | SQLite (Default for rapid setup) / MySQL / PostgreSQL ready |
| **AI / ML Service** | Python 3.10+, Flask REST Microservice, PyTorch, HuggingFace Transformers |
| **Emotion Models** | DistilRoBERTa Multilingual Emotion Classifier, Multilabel Emotion-35, Hybrid Heuristic Safety Engine |
| **Authentication** | Laravel Multi-guard Auth, Role Middleware, Microsoft Entra / OIDC Ready |
| **PWA** | Web App Manifest, Service Worker Caching, Push Notification Framework |

---

## 📊 System Architecture

```text
┌─────────────────────────────────────────────────────────────────────────┐
│                      MAGIREM AI - MinSU Ecosystem                       │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │
           ┌─────────────────────────┼─────────────────────────┐
           ▼                         ▼                         ▼
   ┌──────────────┐          ┌──────────────┐          ┌──────────────┐
   │Student Portal│          │  Counselor   │          │Admin Command │
   │ (PWA / Web)  │          │  Workspace   │          │    Center    │
   └───────┬──────┘          └───────┬──────┘          └───────┬──────┘
           │                         │                         │
           └─────────────────────────┼─────────────────────────┘
                                     ▼
                     ┌───────────────────────────────┐
                     │   Laravel 12 Application Core │
                     │   - RBAC & Domain Gatekeepers │
                     │   - Real-Time Chat & Streaming│
                     │   - Community Moderation Engine│
                     └───────────────┬───────────────┘
                                     │
                 ┌───────────────────┴───────────────────┐
                 ▼                                       ▼
   ┌───────────────────────────┐           ┌───────────────────────────┐
   │ Database & Storage Layer  │           │ Python ML Microservice    │
   │ - Users, Journals, Moods  │◄─────────►│ - Emotion Classification  │
   │ - Private Chat Storage    │   REST    │ - Multilingual NLP (En/Fil│
   │ - Cached Analytics Engine │           │ - Tiered Crisis Detection │
   └───────────────────────────┘           └───────────────────────────┘
```

---

## ⚡ Quick Start

### Prerequisites
- **PHP**: 8.2 or higher (with `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`)
- **Composer**: 2.x
- **Node.js & npm**: Node 18+ and npm 9+
- **Python**: 3.10+ (for ML emotion detection microservice)
- **SQLite** or **MySQL**

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/SLR1130/MAGIREM-AI.git
cd MAGIREM-AI

# 2. Install PHP and Node dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run database migrations & seed demo accounts
php artisan migrate --seed

# 5. Build production frontend assets
npm run build

# 6. (Optional) Start Python ML Emotion Service
cd ml_service
pip install -r requirements.txt
python app.py &
cd ..

# 7. Start the Laravel development server
php artisan serve
```

Visit **`http://localhost:8000`** in your browser.

---

## 🔑 Demo Credentials

| Role | Email | Password | Access Highlights |
|---|---|---|---|
| **Admin** | `admin@mindcare.com` | `admin123` | User approvals, verification queue, campus emotion analytics, moderation |
| **Counselor** | `maria.santos@mindcare.com` | `counselor123` | Student triage, chat with attachment streaming, appointment approvals |
| **Counselor** | `john.reyes@mindcare.com` | `counselor123` | Counselor dashboard, tele-counseling video room, case notes |
| **Student** | `juan.delacruz@student.msu.edu` | `student123` | MAGI AI companion, journaling, coping exercises, community circle |
| **Student** | `maria.clara@student.msu.edu` | `student123` | Mood check-ins, appointment booking, confidential counselor chat |

---

## 🔒 Security & Privacy

- **Data Privacy**: Anonymous journaling mode, decoupled depression risk metrics, and student-controlled data visibility.
- **Attachment Isolation**: Counselor chat file uploads are stored on a private, non-public storage disk streamed exclusively via authenticated routes.
- **Institutional Domain Constraint**: Student registration enforces institutional domain verification (`@minsu.edu.ph`) with unique student ID safeguards.
- **CSRF & Session Hardening**: Strict CSRF verification across all API endpoints, secure session cookie handling, and encrypted password storage via `bcrypt`.
- **Administrative Protection**: Built-in safeguards preventing administrator self-deletion and orphan states.

---

## 🧪 Testing

Execute the comprehensive feature and unit test suite:

```bash
# Run full PHPUnit test suite
php artisan test

# Test specific domains
php artisan test --filter=StudentHeaderNavigationTest
php artisan test --filter=CounselorDashboardTest
php artisan test --filter=AccountVerificationFlowTest
php artisan test --filter=CrisisAppointmentTest
```

---

## 📄 License & Academic Attribution

This software is developed as an academic capstone project for **Mindoro State University (MinSU) - Bongabong Campus**.

- **Academic Year:** 2025–2026
- **Institutional Partner:** University Guidance and Counseling Office
- **License:** [MIT License](LICENSE)

---

<div align="center">

**Made with 🌿 for Mindoro State University Students**  
*Promoting mental wellness, emotional resilience, and accessible guidance.*

</div>
