# R&D Logbook Management System

## 📋 Overview

A comprehensive web-based logbook management system designed for Research & Development laboratories. This system helps track instrument usage, experimental activities, and maintain detailed records with advanced security features.

## ✨ Features

### 🔬 **Laboratory Management**
- **Multi-instrument Support**: Manage various laboratory instruments with customizable parameter matrices
- **Dynamic Form Fields**: Instrument-specific parameter fields that adapt based on equipment type
- **Activity Tracking**: Detailed logging of research activities with start/end times
- **Sample Management**: Track samples, trial codes, and experimental conditions

### 👥 **User Management** - **Role-based Access Control**: Admin, User, and Viewer roles with appropriate permissions
- **Secure Authentication**: Password hashing, CSRF protection, and session management
- **Account Security**: Login attempt limiting with temporary lockouts
- **User Activity Tracking**: Monitor user actions and login history

### 📊 **Reporting & Analytics**
- **Advanced Filtering**: Search by date range, instrument, user, and custom parameters  
- **Data Export**: Export to Excel, CSV, PDF formats
- **Usage Statistics**: Instrument utilization reports and user productivity metrics
- **Maintenance Alerts**: Automated alerts for equipment needing attention

### 🔒 **Security Features**
- **CSRF Protection**: Cross-site request forgery prevention
- **SQL Injection Prevention**: Prepared statements throughout
- **Input Sanitization**: All user inputs properly escaped and validated
- **Audit Logging**: Comprehensive activity logging for compliance
- **File Upload Security**: Secure file handling with type validation

## 🚀 Installation

### **Prerequisites**
- PHP 8.0 or higher
- MySQL 5.7 or higher / MariaDB 10.3+
- Web server (Apache/Nginx)
- Composer (optional, for dependency management)

### **Step 1: Download & Extract**
```bash
git clone [https://github.com/your-repo/logbook-system.git](https://github.com/your-repo/logbook-system.git)
cd logbook-system