<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(1);} if(db()->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='Administrator'")->fetchColumn()){fwrite(STDERR,"Administrator already exists.\n");exit(1);}
$name=trim(readline('Administrator full name: '));$email=trim(readline('Administrator email: '));$password=trim(readline('Administrator password (12+ characters): '));if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Invalid name, email or password.\n");exit(1);}$q=db()->prepare('INSERT INTO users(role_id,full_name,email,password_hash) VALUES(1,?,?,?)');$q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);fwrite(STDOUT,"Administrator account created.\n");
