<?php

// iTexMo SMS API Configuration 
define('ITEXMO_API_CODE', 'TR-KARYL474085_4NVX5'); 
define('ITEXMO_API_PASSWORD', 'm67r]s6fqy'); // 
define('ITEXMO_API_URL', 'https://www.itexmo.com/php_api/api.php');

// Alternative SMS provider configurations 
define('SMS_ENABLED', true); 
define('SMS_TEST_MODE', true); 

define('SMS_BOOKING_TEMPLATE', 'GOMS: Appointment booked for [StudentName] with Counselor [CounselorName] on [Date] at [Time]. Case: [BriefConcern]. Reply STOP to opt out.');
define('SMS_RESCHEDULE_TEMPLATE', 'GOMS: Appointment for [StudentName] rescheduled to [Date] at [Time].');
define('SMS_CANCELLATION_TEMPLATE', 'GOMS: Appointment for [StudentName] on [Date] at [Time] has been cancelled.');
define('SMS_ADVISER_APPOINTMENT_TEMPLATE', 'GOMS: Appointment for your student [StudentName] has been [scheduled/rescheduled/cancelled]. Date: [Date], Time: [Time], Counselor: [CounselorName].');
define('SMS_REMINDER_24H_TEMPLATE', 'Reminder: [StudentName] has counseling session tomorrow at [Time] with [CounselorName].');
define('SMS_REMINDER_1H_TEMPLATE', 'Reminder: [StudentName] has counseling session in 1 hour at [Time].');
?>