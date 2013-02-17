#U Could Finish

This project began in 2011 to help students at the University of Central Florida enroll in classes that fill up quickly. It uses the Guest Search capability of the MyUCF enrollment system to check regularly to identify when it became available for signup (when a 'seat' opened up). Users who were interested in the course are then notified that a space has become available via text message.

The website was only available for a brief time due to conflict with university policies that lead them to block the server's IP address and pursue disciplinary action. More can be read about that on various news stories such as here: http://articles.orlandosentinel.com/2012-08-07/features/os-ucf-student-probation-website-20120807_1_ucf-spokesman-grant-heston-website-servers

As this is an extreme example of simultaneous cURL and preformed some interesting operations for efficiency, I have decided to open source parts of the backend for others to learn from. However the interface with MyUCF is not compatible with the current site nor do I have plans to update it.

##Code Structure

U Could Finish was designed to reduce load on UCF systems as much as possible by only fetching courses requested (instead of pulling every class enrollment total) and combining requests if multiple students wanted the same course. Below is a brief description of each file's function.

###getClass.php
Fetches open seat total for one class using cURL (class ID and term passed in with GET variable). Execution time runs between 15 and 45 seconds.

###updateClasses.php
Manages up to 20 simultaneous getClass.php class checks (20 classes at once) using RollingCurl's cURL multithreading library

###updateCourses.php
Pulls in class details for every class by iterating per college. This was to build the initial search database and was only executed once per semester. Execution time of approximately 15 minutes.

###notifyOpening.php
Alerts users via text message when a course has availability.

###smsHandler.php
Processes inbound response to determine if the user successfully enrolled after availability notification

###inc-sms.php
Most functions have been removed for release, but includes the simple Yes/No determinant function for inbound texts


##License
This is released into the public domain, with the exception of RollingCurl.php which was released under the Apache License 2.0 by Josh Fraser.
