# Phân tích một gadget chain với lỗ hổng PHP Insercure Unserialization
## Mục Lục
## 🧠1. Giới thiệu chung
`Insecure Unserialization` hay `Object Injection` là một lỗ hổng phổ biến trong PHP, xảy ra khi dữ liệu không đáng tin cậy được truyền trực tiếp vào hàm `unserialize()` mà không có kiểm soát hoặc xác thực. Khi đó, kẻ tấn công có thể chèn vào chuỗi dữ liệu các đối tượng được thiết kế đặc biệt `(gadget)` để khai thác các `magic method` như `__wakeup()` hay `__destruct()`, từ đó dẫn đến thực thi mã tùy ý `(RCE – Remote Code Execution)`.

Một ví dụ điển hình là lỗ hổng từng tồn tại trong `Zend Framework` (trước phiên bản `1.12.21`), nơi mà một số class trong framework có thể bị lợi dụng để xây dựng `gadget chain`, tạo điều kiện cho tấn công khi dữ liệu đầu vào bị `unserialize` một cách không an toàn.

Mục tiêu của project này là mô phỏng lại quá trình khai thác thông qua việc xây dựng một webpage mẫu chứa lỗ hổng trên `Zend Framework`, tạo payload bằng công cụ `phpggc`, debug theo luồng `gadget chain`, và cuối cùng là phân tích cũng như đưa ra một số cách phòng chống hiệu quả lỗ hổng này trong thực tế phát triển phần mềm.
## 🐛2. Tổng quan về lỗ hổng Insecure Unserialization trên PHP
Trong PHP, `serialize()` và `unserialize()` là hai hàm dùng để tuần tự hóa và khôi phục các đối tượng hoặc cấu trúc dữ liệu phức tạp. Tuy nhiên, nếu dữ liệu được truyền vào `unserialize()` đến từ nguồn không tin cậy, không được kiểm soát hay xác thực phù hợp (như đầu vào từ người dùng), nó có thể bị lợi dụng để thực hiện hành vi tấn công.

Lỗ hổng `nsecure Unserialization` xảy ra khi dữ liệu đã bị kẻ tấn công kiểm soát được `unserialize` mà không có kiểm tra nghiêm ngặt. Bằng cách tạo ra một chuỗi `(chain)` tuần tự hóa chứa các đối tượng đặc biệt `(gadget)`, kẻ tấn công có thể lợi dụng các phương thức "ma thuật" - `magic method` trong PHP như: `__construct()`, `__destruct()`, `__wakeup()`, `__toString()`,...

Trong chuỗi `gadget chain`, các đối tượng được sắp xếp sao cho khi `unserialize` xảy ra, các phương thức nguy hiểm sẽ được gọi một cách tự động và tuần tự, dẫn đến việc kẻ tấn câng thực hiện các loại tấn công khác nhau, chẳng hạn như Code Injection, SQL Injection, Path Traversal, DDoS,...

Ví dụ điển hình về payload có thể chứa nội dung như sau:
```php 
O:8:"Exploit":1:{s:4:"data";s:13:"rm -rf /var";}
```
Khi unserialize chuỗi trên, nếu class Exploit có chứa `__destruct()` thực hiện `system($this->data)`, thì dòng lệnh sẽ được thực thi ngay lập tức.

Các lỗ hổng này thường rất nguy hiểm do chúng khó bị phát hiện bằng mắt thường và có thể dẫn đến toàn quyền kiểm soát máy chủ nếu không được xử lý đúng cách.

## 3. Tổng quan về Zend Framework
`Zend Framework` là một framework mã nguồn mở mạnh mẽ và phổ biến được dùng để xây dựng các ứng dụng web PHP theo kiến trúc MVC (Model–View–Controller). Với thiết kế hướng đối tượng và hỗ trợ mở rộng, Zend cung cấp nhiều class tiện ích cho việc xử lý log, email, cấu hình, layout,...Tuy nhiên, chính sự đa dạng và phức tạp này cũng tạo điều kiện cho việc hình thành các `gadget chain` nếu không kiểm soát cẩn thận việc tuần tự hóa đối tượng.

Trước phiên bản `1.12.21`, `Zend Framework` tồn tại các lớp như:

`Zend_Log`: hỗ trợ ghi log linh hoạt.

`Zend_Mail`: xử lý gửi email.

`Zend_Config`: quản lý cấu hình theo file hoặc mảng.

`Zend_View` và `Zend_Layout`: hỗ trợ tạo giao diện người dùng động.

Các lớp này có thể bị xâu chuỗi lại với nhau nhờ các phương thức `__destruct()`, `__call()` và `__toString()` để tạo thành một gadget chain nguy hiểm, cho phép kẻ tấn công lợi dụng để thực thi lệnh hệ thống thông qua `unserialize`.

Zend sau đó đã phát hành bản vá trong phiên bản `1.12.21`, loại bỏ hoặc điều chỉnh các hành vi nguy hiểm trong các phương thức ma thuật, đồng thời khuyến cáo không nên `unserialize` dữ liệu không đáng tin cậy.
## 4. Xây dựng webpage chứa lỗ hổng
### 4.1. Mục tiêu
Xây dựng một webpage sử dụng `Zend Framework` chứa đoạn mã có đối tượng được `unserialize()` mà không qua xác thực. Sau đó tiến hành khai thác lỗ hổng `Insecure Unserialization` trên trang web này sử dụng payload tạo từ công cụ `phpggc`.
### 4.2. Công nghệ sử dụng
- Language: `PHP 7.2.3` .
- Framework: `ZendFramework 1.12.20`.
- Server: `Apache 2.4.46 (XAMPP 8.2.4)`.
- Debugger-extension: `Xdebug 3.1.6`.
- IDE: `PHPStorm 2024.3.1.1`.
- Environment: `Localhost`.
### 4.3. Đoạn mã gây ra lỗ hổng
Để nghiên cứu và mô phỏng lỗ hổng `PHP Insecure Unserialization` trên `Zend Framework`, báo cáo xây dựng một đoạn mã thử nghiệm đơn giản như sau:
```php
<?php

class IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $username = $this->getRequest()->getPost('username');
            $password = $this->getRequest()->getPost('password');

            $data = base64_decode($username);
            @unserialize($data); 
        }

        // Hiển thị form HTML...
    }
}
```
*📌 Lưu ý: Đây là một đoạn mã giả lập, có thể không xuất hiện trong ứng dụng thực tế. Mục đích là tái hiện một trang web cho phép nhập đầu vào nguy hiểm dẫn dến việc giải tuần tự dữ liệu không đáng tin cậy — từ đó phân tích sự hoạt động của gadget chain khi được thực thi.*

- Ứng dụng nhận dữ liệu username từ người dùng thông qua phương thức POST.

- Dữ liệu này được `base64_decode()` rồi truyền trực tiếp vào `unserialize()` mà không qua bất kỳ kiểm tra hay xác thực nào.

- Dòng `@unserialize($data);` chính là điểm dễ bị tấn công, đặc biệt nếu dữ liệu gửi đến là một chuỗi gadget chain được xây dựng phục vụ mục đích khai thác.
### 4.4. Quá trình khai thác
#### 4.4.1. Tạo payload
Để tạo payload khai thác lỗ hổng trên trang web được dựng ở trên, sử dụng công cụ [`Phpggc`](https://github.com/ambionics/phpggc). Công cụ này hỗ trợ sẵn rất nhiều `gadget chains` được trích xuất từ các thư viện và framework phổ biến như `Zend Framework`, `Laravel`, `Monolog`, `SwiftMailer`,…
Sử dụng lệnh sau để tạo payload từ gadget chain để khai thác lỗ hổng:
```bash
phpggc zendframework/rce4 'system("start calc");' | base64
```
- `zendframework/rce4`: Tên của `gadget chain` đã được định nghĩa trong phpggc, thực nghiệm sử dụng rce4 trên Zend Framework.

- `system("start calc")`: Lệnh hệ thống sẽ được thực thi nếu khai thác thành công, ở đây là lệnh khởi động calculator trên Windows .

- `base64`: Mã hóa đầu ra để phù hợp với xử lý đầu vào trong đoạn mã thử nghiệm (giải mã bằng base64_decode trước khi unserialize).

Payload được tạo ra dưới dạng base64 như sau: 

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Phpggc_payload.png?raw=true" alt="Phpggc_payload" width="800"/>
    <p align="center"><em>Payload được tạo bằng phpggc</em></p>
</p>

#### 4.4.2. Nhập payload khai thác
Nhập payload khai thác vào index webpage trên Zend Framework đã được giới thiệu trong phần 4.3.

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Zend_index.png?raw=true" alt="Phpggc_payload" width="800"/>
</p>

Vì hàm `unserialize()` giải tuần tự hóa trực tiếp dữ liệu từ trường `username`, tiến hành chèn trực tiếp payload vào trường này: 

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Zend_index_payload.png?raw=true" alt="Phpggc_payload" width="800"/>
</p>



Khi payload chạy thành công, lệnh chèn vào được thực thi, ở đây là mở calculator

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Zend_index_calc.png?raw=true" alt="Phpggc_payload" width="800"/>
</p>

## 5. Phân tích gadget chain
### 5.1.
### 5.2.

