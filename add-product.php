<?php
// Начинаем сессию
session_start();

// Подключаем файл с функциями
require_once '../includes/functions.php';

// Если пользователь не авторизован или не является продавцом, перенаправляем на главную
if (!isLoggedIn() || !isSeller()) {
    header('Location: ../index.php');
    exit;
}

// Подключаемся к базе данных
$pdo = require '../config/database.php';

// Получаем данные текущего пользователя
$user = getCurrentUser();

// Получаем все категории для выбора
$categories = getAllCategories($pdo);

// Обработка формы добавления товара
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category_id = $_POST['category_id'] ?? 0;
    
    // Проверяем, что все поля заполнены
    if (empty($name) || empty($description) || empty($price) || empty($category_id)) {
        $error = 'Все поля обязательны для заполнения';
    } else {
        // Проверяем, загружено ли изображение
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Пожалуйста, загрузите изображение товара';
        } else {
            // Загружаем изображение
            $uploadResult = uploadImage($_FILES['image']);
            
            if (!$uploadResult['success']) {
                $error = $uploadResult['message'];
            } else {
                // Добавляем товар в базу данных
                $result = addProduct($pdo, $name, $description, $price, $uploadResult['file_path'], $user['id'], $category_id);
                
                if ($result['success']) {
                    $success = 'Товар успешно добавлен';
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Подключаем шапку сайта
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-4">
        <a href="/seller/dashboard.php" class="text-zinc-400 hover:text-white transition-colors">
            ← Назад к панели продавца
        </a>
    </div>
    
    <h1 class="text-3xl font-bold mb-6 text-white">Добавить новый товар</h1>
    
    <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6">
        <?php if ($error): ?>
            <div class="bg-red-900/30 border border-red-800 text-red-200 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-900/30 border border-green-800 text-green-200 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
                <p class="mt-2">
                    <a href="/seller/dashboard.php" class="underline">Вернуться к панели продавца</a> или 
                    <a href="/seller/add-product.php" class="underline">добавить еще один товар</a>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="add-product.php" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label for="name" class="text-zinc-300">Название товара</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                    </div>
                    
                    <div class="space-y-2">
                        <label for="price" class="text-zinc-300">Цена (₽)</label>
                        <input
                            id="price"
                            name="price"
                            type="number"
                            min="1"
                            step="1"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                    </div>
                    
                    <div class="space-y-2">
                        <label for="category_id" class="text-zinc-300">Категория</label>
                        <select
                            id="category_id"
                            name="category_id"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="image" class="text-zinc-300">Изображение товара</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-zinc-700 rounded-lg cursor-pointer bg-zinc-800 hover:bg-zinc-700 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 mb-3 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="mb-2 text-sm text-zinc-500">
                                        <span class="font-semibold">Нажмите для загрузки</span> или перетащите файл
                                    </p>
                                    <p class="text-xs text-zinc-500">PNG, JPG или GIF (макс. 5MB)</p>
                                </div>
                                <input id="image" name="image" type="file" accept="image/*" class="hidden" required />
                            </label>
                            <div id="image-preview" class="hidden w-32 h-32 bg-zinc-800 rounded-lg overflow-hidden">
                                <img id="preview-img" src="#" alt="Предпросмотр" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label for="description" class="text-zinc-300">Описание товара</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="12"
                        required
                        class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                    ></textarea>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-white hover:bg-gray-200 text-black py-3 px-4 rounded-md font-medium">
                Добавить товар
            </button>
        </form>
    </div>
</div>

<script>
    // Предпросмотр изображения
    document.getElementById('image').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-img').src = e.target.result;
                document.getElementById('image-preview').classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php
// Подключаем подвал сайта
include '../includes/footer.php';
?>

