<?php
// PhilSMS API Configuration
define('PHILSMS_API_KEY', '815|tpwc8LfPn1tE3WjdiDih1eezOp0nczuz4Oc93jSM5c75922a');
define('PHILSMS_SMS_ENDPOINT', 'https://dashboard.philsms.com/api/v3/sms/send');

// SMS Settings
define('SMS_ENABLED', true);
define('SMS_TEST_MODE', false); // Set to true initially for testing
define('SMS_SENDER_ID', 'GOMS'); // Sender ID for SMS

// SMS Templates
define('SMS_BOOKING_TEMPLATE', 'GOMS: Appointment booked for [StudentName] with Counselor [CounselorName] on [Date] at [Time]. Case: [BriefConcern]. Reply STOP to opt out.');
define('SMS_RESCHEDULE_TEMPLATE', 'GOMS: Appointment for [StudentName] rescheduled to [Date] at [Time].');
define('SMS_CANCELLATION_TEMPLATE', 'GOMS: Appointment for [StudentName] on [Date] at [Time] has been cancelled.');
define('SMS_ADVISER_APPOINTMENT_TEMPLATE', 'GOMS: Appointment for your student [StudentName] has been [scheduled/rescheduled/cancelled]. Date: [Date], Time: [Time], Counselor: [CounselorName].');
define('SMS_REMINDER_24H_TEMPLATE', 'Reminder: [StudentName] has counseling session tomorrow at [Time] with [CounselorName].');
define('SMS_REMINDER_1H_TEMPLATE', 'Reminder: [StudentName] has counseling session in 1 hour at [Time].');
?>