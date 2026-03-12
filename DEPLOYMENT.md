# Deployment Guide - CEFI Reservation System

The system is now ready for production deployment. Follow these steps to set up the application on a new server.

## 1. Database Setup
1. Create a new database named `cefi_reservation` on your production MySQL server.
2. Import the `database.sql` file into the new database.
   - This file includes the full schema, the default admin user, and initial facility data.

## 2. Configuration
1. Open `config/db.php`.
2. Update the `$host`, `$user`, and `$pass` variables to match your production database credentials.
3. Ensure `config/session.php` has a secure `session_save_path` if required by your hosting environment.

## 3. Security
1. **API Key**: The current API key is set to `cefi_api_2026_secure_key` in `config/api_auth.php`. You may want to change this for production.
2. **Admin Password**: The default password for the `admin` user is `admin123`. Change this immediately via the database or by creating a new admin account.
3. **SSL**: Ensure your site is served over HTTPS to protect the login session and CSRF tokens.

## 4. Maintenance
- Regular backups of the `cefi_reservation` database are highly recommended.
- Audit logs are stored in the `audit_logs` table; you can export these periodically via the Audit Trail page.

---
**Status: READY FOR DEPLOYMENT**
