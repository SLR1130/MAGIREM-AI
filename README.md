# MAGIREM AI

MAGIREM AI is a Laravel and Python application for student emotional wellness, with an AI companion, journaling, wellness resources, community, and counseling workflows.

## Local development

1. Install compatible PHP, Composer, Node.js, Python, and a database.
2. Install PHP and frontend dependencies with `composer install` and `npm install`.
3. Copy `.env.example` to `.env` and configure local values privately. Never commit credentials or personal data.
4. Configure an isolated development database and the Python service before running the application.
5. Build assets with `npm run build` and start Laravel using your normal local setup.

Run PHP tests only against the isolated testing database configured in `phpunit.xml`; run frontend type checks with `npm run typecheck`.

## Privacy and model files

This public source snapshot does not include environment secrets, user data, backups, training datasets, or trained model checkpoints. The Python service requires compatible trained model files and its own dependencies to perform inference; obtain them through an approved private distribution channel. An ordinary clone is not a ready-to-run production deployment.

Never use real student information in tests, and obtain the necessary institutional privacy approvals before enabling registration or processing personal data.
