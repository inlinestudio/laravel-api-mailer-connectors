[![run-tests](https://github.com/inlinestudio/laravel-api-mailer-connectors/actions/workflows/run-tests.yml/badge.svg)](https://github.com/inlinestudio/laravel-api-mailer-connectors/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/inlinestudio/laravel-api-mailer-connectors/branch/main/graph/badge.svg?token=CytArLiyWH)](https://codecov.io/gh/inlinestudio/laravel-api-mailer-connectors)
#  Laravel API Mail driver - Mail Driver for Office365 and (soon) Google


Mail driver for the [Laravel framework](https://laravel.com/) to send emails using Microsoft Graph without user authentication and SMTP. Only specify the E-Mail-Address in the FROM-Header of the E-Mail and this package will send the email trough the providers API and put the sent E-Mail in the sender's Mailbox sent folder.

**O365 Key features:**

 - Send E-Mails with the Microsoft Graph-Api instead of the SMTP driver
 - Automatically puts the E-Mail in the Sent folder of the user in the From-Header
 - One Application per Organization
 - Supports multiple Domains
 - Supports large file attachments
 - Faster and Error-less than the SMTP protocol

---

##  Install the Package
```
  composer require inlinestudio/mailconnectors
```
---

##  Configure


### O365

To use this package you have to register your application [here](https://go.microsoft.com/fwlink/?linkid=2083908). More informations [here](https://docs.microsoft.com/en-us/graph/auth-register-app-v2).

To obtain needed config values use this [instructions](https://docs.microsoft.com/en-us/graph/auth-v2-service):

  - Open the [Azure Active Directory-Portal](https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/Overview)) with your Office365 Admin-User
  - Open the Section Manage > [App-Registrations](https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/RegisteredApps)
  - Create a new App
  - Within the App under `Manage` >  `API-Permissions` > `Application Permissions` add the `Mail.ReadWrite` and the `Mail.Send` permission (Microsoft Graph > Application Permissions > Mail > Mail.ReadWrite and Microsoft Graph > Application Permissions > Mail > Mail.Send)
  - After saving the permission apply the Admin-Permission for your organization
  - In the Section Manage > Certificates and Secrets create a new Client Secret with Expiration = 24 months, this you need later for the `.env` - Variable  `OFFICE365MAIL_CLIENT_SECRET`

The `Mail.ReadWrite` Permission is needed when sending large attachments (>4MB).

#### .env - File
```
MAIL_MAILER=O365
OFFICE365MAIL_CLIENT_ID=YOUR-MS-GRAPH-CLIENT-ID
OFFICE365MAIL_TENANT=YOUR-MS-GRAPH-TENANT-ID
OFFICE365MAIL_CLIENT_SECRET=YOUR-MS-GRAPH-CLIENT-SECRET
```

#### config/mail.php - add to mailer configuration array

```
'O365' => [
    'transport' => 'O365',
],
```

---
### Google (soon)
---

##  Copyright and license

Copyright © InlineStudio. All Rights Reserved. Licensed under the MIT [license](LICENSE).