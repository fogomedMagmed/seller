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

// Получаем товары продавца
$products = [];
try {
    if ($pdo instanceof PDO) {
        // Проверяем, существует ли таблица products
        $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
        if ($stmt->rowCount() > 0) {
            // Получаем товары продавца
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.seller_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$user['id']]);
            $products = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    // В случае ошибки просто показываем пустой список товаров
}

// Получаем статистику продаж
$stats = [
    'total_products' => count($products),
    'active_products' => 0,
    'total_sales' => 0,
    'total_revenue' => 0
];

foreach ($products as $product) {
    if ($product['status'] === 'active') {
        $stats['active_products']++;
    }
}

try {
    if ($pdo instanceof PDO) {
        // Проверяем, существует ли таблица orders
        $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
        if ($stmt->rowCount() > 0) {
            // Получаем статистику продаж
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT o.id) as total_sales, SUM(oi.price * oi.quantity) as total_revenue
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.seller_id = ? AND o.status IN ('paid', 'completed')
            ");
            $stmt->execute([$user['id']]);
            $salesStats = $stmt->fetch();
            
            if ($salesStats) {
                $stats['total_sales'] = $salesStats['total_sales'] ?? 0;
                $stats['total_revenue'] = $salesStats['total_revenue'] ?? 0;
            }
        }
    }
} catch (PDOException $e) {
    // В случае ошибки оставляем нулевые значения
}

// Подключаем шапку сайта
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-white">Панель продавца</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6">
            <h3 class="text-lg font-medium mb-2 text-white">Всего товаров</h3>
            <p class="text-3xl font-bold text-white"><?php echo $stats['total_products']; ?></p>
        </div>
        
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6">
            <h3 class="text-lg font-medium mb-2 text-white">Активных товаров</h3>
            <p class="text-3xl font-bold text-white"><?php echo $stats['active_products']; ?></p>
        </div>
        
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6">
            <h3 class="text-lg font-medium mb-2 text-white">Всего продаж</h3>
            <p class="text-3xl font-bold text-white"><?php echo $stats['total_sales']; ?></p>
        </div>
        
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6">
            <h3 class="text-lg font-medium mb-2 text-white">Общий доход</h3>
            <p class="text-3xl font-bold text-white"><?php echo $stats['total_revenue']; ?> ₽</p>
        </div>
    </div>
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Мои товары</h2>
        <a href="/seller/add-product.php" class="px-4 py-2 bg-white text-black hover:bg-gray-200 rounded-md transition-colors">
            Добавить товар
        </a>
    </div>
    
    <?php if (empty($products)): ?>
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-8 text-center">
            <p class="text-zinc-300 mb-4">У вас пока нет товаров</p>
            <a href="/seller/add-product.php" class="inline-block px-6 py-3 bg-white text-black font-medium rounded-lg hover:bg-gray-200 transition-colors">
                Добавить первый товар
            </a>
        </div>
    <?php else: ?>
        <div class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-zinc-800">
                    <tr>
                        <th class="py-3 px-4 text-left text-white">Товар</th>
                        <th class="py-3 px-4 text-center text-white">Категория</th>
                        <th class="py-3 px-4 text-center text-white">Цена</th>
                        <th class="py-3 px-4 text-center text-white">Статус</th>
                        <th class="py-3 px-4 text-center text-white">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr class="border-t border-zinc-800">
                            <td class="py-4 px-4">
                                <div class="flex items-center">
                                    <img src="../<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="w-16 h-16 object-cover rounded mr-4">
                                    <div>
                                        <h3 class="text-white font-medium"><?php echo $product['name']; ?></h3>
                                        <p class="text-zinc-400 text-sm truncate max-w-xs"><?php echo $product['description']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-center text-white"><?php echo $product['category_name']; ?></td>
                            <td class="py-4 px-4 text-center text-white"><?php echo $product['price']; ?> ₽</td>
                            <td class="py-4 px-4 text-center">
                                <?php if ($product['status'] === 'active'): ?>
                                    <span class="inline-block px-2 py-1 bg-green-900/30 text-green-200 rounded text-xs">Активен</span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-1 bg-red-900/30 text-red-200 rounded text-xs">Неактивен</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="/seller/edit-product.php?id=<?php echo $product['id']; ?>" class="text-blue-400 hover:text-blue-300">
                                        Редактировать
                                    </a>
                                    <a href="/seller/delete-product.php?id=<?php echo $product['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('Вы уверены, что хотите удалить этот товар?')">
                                        Удалить
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// Подключаем подвал сайта
include '../includes/footer.php';
?>

