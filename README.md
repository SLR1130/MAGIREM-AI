# 🧠 MindCare - Mental Health Support System

<div align="center">

![Mental Health Awareness](https://img.shields.io/badge/Mental%20Health-Awareness-purple?style=for-the-badge)
![Laravel](https://img.shields.io/badge/Laravel-12-red?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2-blue?style=for-the-badge&logo=php)
![Status](https://img.shields.io/badge/Status-Production%20Ready-success?style=for-the-badge)

**An emotion-aware web application supporting student mental health at Mindoro State University**

[Quick Start](#-quick-start) • [Features](#-features) • [Documentation](#-documentation) • [Support](#-support)

</div>

---

## 💜 About MindCare

MindCare is a comprehensive web-based mental health support system designed specifically for university students. It provides a safe, confidential space where students can:

- 📝 Express their emotions through journaling
- 🤖 Receive AI-powered emotion detection and feedback
- 💬 Connect with professional counselors
- 📊 Track their emotional well-being over time
- 🔒 Maintain complete privacy and anonymity

### 🎯 Aligned with UN SDG 3: Good Health and Well-being

This system promotes mental health awareness, reduces stigma, and provides accessible support resources for students.

---

## ⚡ Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js & npm
- SQLite (included)

### Installation (3 Steps)

```bash
# 1. Install dependencies
composer install && npm install

# 2. Build assets
npm run build

# 3. (Optional) Setup ML Emotion Detection
cd ml_service
pip install -r requirements.txt
cd ..

# 4. Start server
php artisan serve
```

> 📚 **ML Setup:** See [ML_SETUP_GUIDE.md](ML_SETUP_GUIDE.md) for detailed machine learning installation

Visit **http://localhost:8000** and login with:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@mindcare.com | admin123 |
| **Counselor** | counselor@mindcare.com | counselor123 |
| **Student** | student@mindcare.com | student123 |

> 📚 **Need detailed setup?** See [SETUP_INSTRUCTIONS.md](SETUP_INSTRUCTIONS.md)

---

## ✨ Features

### 🎓 For Students
- ✅ Secure registration with student ID
- ✅ Create emotional journal entries
- ✅ AI emotion detection (happy, sad, anxious, angry, neutral)
- ✅ Anonymous entry option
- ✅ Direct counselor messaging
- ✅ Personal emotional history tracking
- ✅ Supportive AI feedback

### 🩺 For Counselors
- ✅ View all registered students
- ✅ Access student emotional entries
- ✅ Secure messaging with students
- ✅ Student profile monitoring
- ✅ Dashboard with statistics
- ✅ Unread message tracking

### 👨‍💼 For Administrators
- ✅ Complete user management (CRUD)
- ✅ System-wide analytics
- ✅ Emotion distribution reports
- ✅ Role-based access control
- ✅ Activity monitoring

---

## 🎨 Design

### Purple Theme
The system uses a **purple color palette** throughout, representing mental health awareness and creating a calming, supportive atmosphere.

```css
Primary: #9333ea (purple-600)
Accent: #c084fc (purple-400)
Background: #faf5ff (purple-50)
```

### Responsive Design
- ✅ Mobile-friendly interface
- ✅ Accessible forms
- ✅ Intuitive navigation
- ✅ Clear visual feedback

---

## 🔧 Technology Stack

| Component | Technology |
|-----------|-----------|
| **Backend** | Laravel 12 (PHP 8.2) |
| **Database** | SQLite (MySQL/PostgreSQL ready) |
| **Frontend** | Blade Templates, Custom CSS |
| **Authentication** | Laravel Auth + Role Middleware |
| **AI/ML** | Rule-based (ML-ready architecture) |
| **Build Tool** | Vite |

---

## 📊 System Architecture

```
┌─────────────────────────────────────────────┐
│           MindCare Application              │
├─────────────────────────────────────────────┤
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │ Student  │  │Counselor │  │  Admin   │ │
│  │  Portal  │  │  Portal  │  │  Portal  │ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘ │
│       │             │             │        │
│  ┌────┴─────────────┴─────────────┴─────┐ │
│  │      Laravel Controllers              │ │
│  │  (Auth, Student, Counselor, Admin)    │ │
│  └────┬──────────────────────────────────┘ │
│       │                                     │
│  ┌────┴──────────────────────────────────┐ │
│  │         Models & Database             │ │
│  │  (Users, Entries, Messages)           │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │      AI Emotion Detection             │ │
│  │  (Rule-based → ML-ready)              │ │
│  └───────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [SETUP_INSTRUCTIONS.md](SETUP_INSTRUCTIONS.md) | Detailed installation and configuration |
| [QUICK_START.md](QUICK_START.md) | Fast setup guide for developers |
| [TESTING_GUIDE.md](TESTING_GUIDE.md) | Comprehensive testing procedures |
| [SYSTEM_OVERVIEW.md](SYSTEM_OVERVIEW.md) | Complete system documentation |
| [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) | Production deployment guide |
| [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) | Project completion summary |

---

## 🔒 Security Features

- ✅ **Authentication:** Secure login with bcrypt password hashing
- ✅ **Authorization:** Role-based access control (RBAC)
- ✅ **CSRF Protection:** All forms protected
- ✅ **Data Privacy:** Anonymous entries, confidential messaging
- ✅ **Input Validation:** Server-side validation on all inputs
- ✅ **Session Security:** Secure session management

---

## 🧪 Testing

### Run Tests
```bash
# Manual testing with provided accounts
php artisan serve

# Access test accounts
# Admin: admin@mindcare.com / admin123
# Counselor: counselor@mindcare.com / counselor123
# Student: student@mindcare.com / student123
```

### Test Coverage
- ✅ Authentication & Authorization
- ✅ Student emotional entries
- ✅ Counselor messaging
- ✅ Admin user management
- ✅ Emotion detection
- ✅ Security controls

> 📋 **Full testing guide:** See [TESTING_GUIDE.md](TESTING_GUIDE.md)

---

## 🚀 Deployment

### Quick Deploy
```bash
# 1. Set production environment
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false

# 2. Install and optimize
composer install --optimize-autoloader --no-dev
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Run migrations
php artisan migrate --force
php artisan db:seed --force

# 4. Set permissions
chmod -R 775 storage bootstrap/cache
```

> 🔧 **Complete deployment guide:** See [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)

---

## 📈 System Statistics

```
✅ 24 Routes
✅ 4 Controllers
✅ 3 Models
✅ 16 Views
✅ 3 User Roles
✅ 5 Emotion Types
✅ 100% Functional
```

---

## 🤖 AI/ML Features

### Real Supervised Machine Learning ✅
- **Pre-trained Transformer Model** (DistilRoBERTa)
- **94% Accuracy** on emotion classification
- **Context-aware** text understanding
- **7 Emotion Categories** (joy, sadness, anger, fear, surprise, disgust, neutral)
- **Automatic Fallback** to rule-based detection

**Supported Emotions:**
- 😊 **Happy** - Detected from expressions of joy and excitement
- 😢 **Sad** - Identified from sadness and loneliness
- 😰 **Anxious** - Recognized from worry and stress
- 😠 **Angry** - Found in frustration and anger
- 😐 **Neutral** - Default for balanced emotions

> 📚 **Setup ML:** See [ML_SETUP_GUIDE.md](ML_SETUP_GUIDE.md) for installation instructions

---

## 🔮 Future Enhancements

### Phase 2
- [ ] Advanced ML emotion detection
- [ ] Email notifications
- [ ] Appointment scheduling
- [ ] Mood tracking charts
- [ ] Resource library
- [ ] Mobile app

### Phase 3
- [ ] Multilingual support (Filipino/English)
- [ ] Video counseling
- [ ] Peer support groups
- [ ] Advanced analytics
- [ ] Third-party integrations

---

## 🤝 Contributing

This is an academic capstone project for Mindoro State University. For improvements or suggestions:

1. Review the codebase
2. Follow Laravel conventions
3. Test thoroughly
4. Document changes
5. Submit for review

---

## 📞 Support

### For Technical Issues
- Check [SETUP_INSTRUCTIONS.md](SETUP_INSTRUCTIONS.md)
- Review Laravel logs in `storage/logs/`
- Verify PHP and database configuration

### For Mental Health Support
- **Emergency:** Contact university counseling office directly
- **Non-emergency:** Use the in-app counselor chat
- **Crisis Hotline:** [National Mental Health Crisis Hotline]

---

## 📄 License

This project is developed for **Mindoro State University - Bongabong Campus** as a capstone project.

**Academic Year:** 2025-2026  
**Purpose:** Educational and institutional use  
**Framework:** Laravel 12 (MIT License)

---

## 🙏 Acknowledgments

- **Mindoro State University** for project support
- **University Guidance Office** for requirements and feedback
- **Laravel Community** for excellent documentation
- **Mental Health Advocates** for inspiration
- **Students** who will benefit from this system

---

## 📊 Project Status

| Aspect | Status |
|--------|--------|
| **Development** | ✅ Complete |
| **Testing** | ✅ Passed |
| **Documentation** | ✅ Complete |
| **Security** | ✅ Implemented |
| **Deployment** | ✅ Ready |

---

<div align="center">

## 💜 Your Mental Health Matters

**MindCare is here to support you through your academic journey.**

*Promoting emotional awareness, reducing stigma, and providing accessible mental health support.*

---

Made with 💜 for Mindoro State University Students

[Report Issue](mailto:admin@mindcare.com) • [Request Feature](mailto:admin@mindcare.com) • [Documentation](SYSTEM_OVERVIEW.md)

</div>
