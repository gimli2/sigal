#!/bin/bash
java -jar _work/compiler.jar --js_output_file ./js/lazy.min.js ./js/lazy.js
java -jar _work/compiler.jar --js_output_file ./js/sigal.min.js ./js/sigal.js
php build.php
