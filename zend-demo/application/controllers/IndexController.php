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

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dang nhap</title>
    <style>
        body {
            background-color: #f9f9f9;
            font-family: Arial;
            text-align: center;
            padding-top: 100px;
        }
        .hop-dang-nhap {
            background-color: #fff;
            display: inline-block;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        input[type="text"], input[type="password"] {
            width: 260px;
            padding: 10px;
            margin: 10px 0;
        }
        input[type="submit"] {
            background-color: #007BFF;
            color: white;
            padding: 10px 20px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="login_form">
        <h2>Welcome Back !!!</h2>
        <form method="post">
            <input type="text" name="username" placeholder="Username"><br>
            <input type="password" name="password" placeholder="Password"><br>
            <input type="submit" value="LOGIN">
        </form>
    </div>
</body>
</html>
HTML;
    }
}

