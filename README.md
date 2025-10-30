# Personal Salesmen Module for PrestaShop 8 & 9

[![PrestaShop](https://img.shields.io/badge/PrestaShop-8.x%20%7C%209.x-blue.svg)](https://www.prestashop.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D7.2.5-blue.svg)](https://php.net/)

## 📋 Description

**Personal Salesmen** is a comprehensive PrestaShop module that allows you to assign specific customers or customer groups to employees. This enables each salesperson to manage only their assigned customers, providing better organization and access control in your back office.

### Key Features

- ✅ **Customer Assignment**: Assign individual customers to specific employees
- ✅ **Group Assignment**: Assign entire customer groups to employees
- ✅ **Access Restriction**: Employees only see customers, orders, and addresses assigned to them
- ✅ **Email Notifications**: Automatic email alerts when assigned customers place orders
- ✅ **SuperAdmin Override**: SuperAdmin always has full access to all data
- ✅ **Modern Architecture**: Built with PrestaShop 8/9 best practices
- ✅ **Grid Integration**: Seamless integration with PrestaShop's modern Grid system
- ✅ **Multi-language Support**: Ready for internationalization

---

## 🚀 Installation

### Requirements

- PrestaShop 8.0.0 or higher (compatible with 9.x)
- PHP 7.2.5 or higher
- **No Composer required** - Works out of the box!

### Installation Steps

1. **Download the module** and extract it to your PrestaShop modules directory:
   ```bash
   cd /path/to/prestashop/modules/
   unzip personalsalesmen.zip
   ```

2. **Install the module** from PrestaShop Back Office:
   - Go to **Modules > Module Manager**
   - Search for "Personal Salesmen"
   - Click **Install**

3. **Configure the module**:
   - After installation, click **Configure**
   - Enable/disable access restrictions
   - Enable/disable email notifications

---

## 📖 Usage

### Creating Assignments

1. Go to **Customers > Personal Salesmen** in your back office
2. Click **New Assignment**
3. Select:
   - **Employee**: The salesperson who will manage the assignment
   - **Type**: Choose between Customer (individual) or Group (all customers in a group)
   - **Target**: Select the specific customer or group
4. Click **Save**

### How It Works

#### For SuperAdmin (Profile ID 1)
- Can see **all customers, orders, and addresses**
- Can manage all assignments
- Access restrictions do not apply

#### For Regular Employees
- Can only see customers assigned to them (directly or through groups)
- Can only see orders from their assigned customers
- Can only see addresses from their assigned customers
- Cannot access assignment management

### Access Control

The module modifies the following PrestaShop grids:
- **Customers Grid** (`AdminCustomers`)
- **Orders Grid** (`AdminOrders`)
- **Addresses Grid** (`AdminAddresses`)

When an employee tries to access a resource directly (via URL), the module checks permissions and blocks unauthorized access.

### Email Notifications

When enabled, employees receive an email notification when:
- One of their assigned customers places a new order
- The email includes order details and a direct link to manage the order

Email templates are available in multiple languages:
- Spanish (`es/new_order_assigned.html`)
- English (`en/new_order_assigned.html`)

---

## 🏗️ Architecture

### Directory Structure

```
personalsalesmen/
├── config/
│   ├── routes.yml              # Symfony routes
│   └── services.yml            # Dependency injection
├── sql/
│   ├── install.php             # Database schema
│   └── uninstall.php           # Cleanup script
├── src/
│   ├── Controller/
│   │   └── Admin/
│   │       └── AssignmentAdminController.php
│   ├── Entity/
│   │   └── Assignment.php      # Doctrine entity
│   ├── Grid/
│   │   ├── Definition/
│   │   │   └── AssignmentGridDefinitionFactory.php
│   │   └── Query/
│   │       ├── CustomerQueryModifier.php
│   │       ├── OrderQueryModifier.php
│   │       └── AddressQueryModifier.php
│   ├── Hook/
│   │   └── MailNotificationHook.php
│   ├── Repository/
│   │   └── AssignmentRepository.php
│   └── Service/
│       ├── AccessControlService.php
│       └── AssignmentService.php
├── views/
│   └── templates/
│       ├── admin/
│       │   ├── index.html.twig
│       │   ├── assignments.html.twig
│       │   └── configure.html.twig
│       └── emails/
│           ├── en/
│           │   └── new_order_assigned.html
│           └── es/
│               └── new_order_assigned.html
├── composer.json
├── personalsalesmen.php        # Main module file
└── README.md
```

### Database Schema

The module creates one table:

**`ps_personalsalesmen_assignment`**
- `id_assignment` - Primary key
- `id_employee` - Employee ID
- `id_customer` - Customer ID (nullable)
- `id_group` - Group ID (nullable)
- `active` - Status (1 = active, 0 = inactive)
- `date_add` - Creation date
- `date_upd` - Last update date

**Constraints:**
- Either `id_customer` OR `id_group` must be set (not both, not neither)
- Unique constraint on `(id_employee, id_customer, id_group)`

### Hooks Used

- `actionAdminControllerSetMedia` - Block direct access to unauthorized resources
- `actionCustomerGridQueryBuilderModifier` - Filter customers grid
- `actionOrderGridQueryBuilderModifier` - Filter orders grid
- `actionAddressGridQueryBuilderModifier` - Filter addresses grid
- `actionValidateOrder` - Send email notifications
- `displayBackOfficeHeader` - Add custom CSS/JS if needed

---

## ⚙️ Configuration

### Module Settings

Access via **Modules > Module Manager > Personal Salesmen > Configure**

**Enable Access Restriction**
- When enabled: Employees see only their assigned customers
- When disabled: All employees see all customers (module is passive)

**Email Notifications**
- When enabled: Employees receive emails for new orders from assigned customers
- When disabled: No notifications are sent

### Configuration Constants

- `PSM_RESTRICTION_ENABLED` - Enable/disable access restrictions (1/0)
- `PSM_EMAIL_NOTIFICATIONS` - Enable/disable email notifications (1/0)

---

## 🔧 Development

### Custom Autoloader

The module includes a custom PSR-4 autoloader (`autoload.php`) that doesn't require Composer. All classes under the `PrestaShop\Module\PersonalSalesmen` namespace are automatically loaded.

**Note:** Composer is **not required** for this module to work. The `composer.json` file is included only for package management and development purposes.

### Adding New Languages

To add email templates for a new language:

1. Create a new directory: `views/templates/emails/{iso_code}/`
2. Copy and translate `new_order_assigned.html`
3. Update `MailNotificationHook::getEmailSubject()` with the new language

### Extending the Module

#### Add Custom Query Modifiers

```php
// In your custom code
$modifier = new CustomerQueryModifier($accessControl, $hookDispatcher);
$modifier->applyRestriction($queryBuilder, 'c');
```

#### Check Access Programmatically

```php
$accessControl = new AccessControlService($context);

// Check if employee can see everything
if ($accessControl->canSeeEverything()) {
    // Show all data
}

// Check specific customer access
if ($accessControl->canAccessCustomer($customerId)) {
    // Show customer data
}

// Get allowed customer IDs
$allowedIds = $accessControl->getAllowedCustomerIds();
```

---

## 🐛 Troubleshooting

### Common Issues

**Issue: Employees still see all customers**
- Solution: Check that "Enable Access Restriction" is turned ON in module settings
- Verify the employee's profile is not SuperAdmin (Profile ID = 1)

**Issue: No assignments visible**
- Solution: Ensure assignments are created in **Customers > Personal Salesmen**
- Check that assignments are marked as "Active"

**Issue: Email notifications not working**
- Solution: Verify "Email Notifications" is enabled in settings
- Check PrestaShop's email configuration (SMTP settings)
- Review PrestaShop logs for email errors

**Issue: Database errors during installation**
- Solution: Check database user permissions
- Verify PrestaShop version compatibility (8.0+)

### Debug Mode

To enable detailed logging:

```php
// In personalsalesmen.php
define('PSM_DEBUG', true);
```

---

## 📝 Changelog

### Version 5.0.0 (Current)
- ✅ Complete module rewrite for PrestaShop 8/9
- ✅ Modern Symfony architecture
- ✅ Grid system integration
- ✅ Doctrine entities
- ✅ Improved access control
- ✅ Email notification system
- ✅ Multi-language support
- ✅ Comprehensive documentation

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This module is licensed under the **MIT License**.

```
Copyright (c) 2025 Community

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
```

---

## 👥 Authors

- **Community** - Initial work and maintenance

---

## 🔗 Links

- [PrestaShop Developer Documentation](https://devdocs.prestashop-project.org/)
- [PrestaShop Forums](https://www.prestashop.com/forums/)
- [Report Issues](https://github.com/yourusername/personalsalesmen/issues)

---

## ✨ Future Enhancements

Planned features for future versions:

- 📊 **Advanced Statistics**: Sales reports per employee
- 💰 **Commission System**: Calculate commissions based on sales
- 👥 **Multi-Assignment**: Allow multiple employees per customer
- 🗺️ **Territory Management**: Geographic assignment rules
- 🔔 **Advanced Notifications**: In-app notifications, webhooks
- 📱 **Mobile App Integration**: API endpoints for mobile apps
- 🤖 **Auto-Assignment**: Rules-based automatic customer assignment
- 📈 **Analytics Dashboard**: Visual performance metrics

---

**Made with ❤️ for the PrestaShop Community**
