# Phân tích một gadget chain với lỗ hổng PHP Insercure Unserialization
## Mục Lục
## 1. Giới thiệu

Lỗ hổng Insecure Deserialization trong PHP, hay còn gọi là PHP Object Injection, cho phép kẻ tấn công thực thi các hành vi nguy hiểm như Remote Code Execution (RCE), SQL Injection, hoặc Path Traversal, tùy thuộc vào ngữ cảnh. Lỗ hổng này xảy ra khi dữ liệu đầu vào không được kiểm tra đúng cách trước khi được chuyển đến hàm unserialize() của PHP.​

Báo cáo này phân tích một gadget chain khai thác lỗ hổng unserialize trong Zend Framework ≤ 1.12.20
