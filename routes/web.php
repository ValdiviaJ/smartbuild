<?php

// use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


// prueba nomas es estoooooooooooooooooooooo
// use Illuminate\Support\Facades\File;
// use Illuminate\Support\Facades\Response;

// // Ruta para servir archivos del build (CSS, JS, imágenes)
// Route::get('/{path}', function ($path) {
//     $file = public_path("build/{$path}");

//     if (File::exists($file)) {
//         return Response::file($file);
//     }

//     return File::get(public_path("build/index.html"));
// })->where('path', '^(static/.*|.*\.(js|css|png|jpg|jpeg|svg|json))$');

// // Fallback para React Router
// Route::get('/{any}', function () {
//     return File::get(public_path("build/index.html"));
// })->where('any', '.*');
