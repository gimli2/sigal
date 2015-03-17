@java -jar _work\compiler.jar --js_output_file ./js/lazy.min.js ./js/lazy.js
@java -jar _work\compiler.jar --js_output_file ./js/sigal.min.js ./js/sigal.js
@_work\tools\PHP-5.3\php.exe %~dp0build.php %*
