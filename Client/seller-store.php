<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get products for this seller
$products_query = "SELECT * FROM products WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($products_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Store - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .product-card {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-2);
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .product-image {
            height: 180px;
            background: var(--navy-3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        .product-info {
            padding: 1rem;
        }
        .product-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .product-stock {
            font-size: 0.85rem;
            color: var(--text-3);
            margin-bottom: 0.5rem;
        }
        .product-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-inactive { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>My Store</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
                <a class="sb-item" href="activity.php"><span class="sb-icon">📋</span><span class="sb-text">Activity Log</span></a>
                <a class="sb-item" href="settings.php"><span class="sb-icon">⚙️</span><span class="sb-text">Settings</span></a>
                <a class="sb-item" href="compare.php"><span class="sb-icon">⚖️</span><span class="sb-text">Compare</span></a>
                <a class="sb-item" href="forecast.php"><span class="sb-icon">🔮</span><span class="sb-text">Forecast</span></a>
                <a class="sb-item" href="compliance.php"><span class="sb-icon">✅</span><span class="sb-text">Compliance</span></a>
                <a class="sb-item" href="email.php"><span class="sb-icon">📧</span><span class="sb-text">Email Report</span></a>
            </div>
            <div class="sb-footer">
                <div class="sb-user">
                    <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                    <div class="sb-user-info">
                        <p><?php echo htmlspecialchars($user['full_name']); ?></p>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="btn-sb-logout">Sign Out</a>
            </div>
        </div>
        
        <div id="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h2>My Store</h2>
                    <p>Manage your products and inventory</p>
                </div>
                <div class="topbar-right">
                    <button class="btn btn-primary" onclick="openProductModal()">+ Add Product</button>
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($products); ?></div>
                        <div>Total Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php
                            $activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
                            echo $activeCount;
                            ?>
                        </div>
                        <div>Active Listings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php
                            $totalStock = array_sum(array_column($products, 'stock'));
                            echo $totalStock;
                            ?>
                        </div>
                        <div>Total Stock</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            ₱<?php
                            $totalValue = array_sum(array_map(fn($p) => $p['price'] * $p['stock'], $products));
                            echo number_format($totalValue, 0);
                            ?>
                        </div>
                        <div>Inventory Value</div>
                    </div>
                </div>
                
                <!-- Product Grid -->
                <div class="product-grid" id="product-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php echo $product['image_url'] ? "<img src='{$product['image_url']}' style='width:100%;height:100%;object-fit:cover;'>" : '📦'; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock">Stock: <?php echo $product['stock']; ?> units</div>
                            <div>
                                <span class="product-status status-<?php echo $product['status']; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </div>
                            <div class="product-actions">
                                <button class="btn btn-xs btn-secondary" onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                                <button class="btn btn-xs btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($products)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🛍️</div>
                    <h3>No Products Yet</h3>
                    <p>Start adding products to your store</p>
                    <button class="btn btn-primary" onclick="openProductModal()">Add Your First Product</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="product-modal" class="modal-overlay hidden" onclick="closeProductModal(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modal-title">Add Product</h3>
                <button class="modal-close" onclick="closeProductModal()">✕</button>
            </div>
            <div id="modal-body">
                <form id="product-form" onsubmit="saveProduct(event)">
                    <input type="hidden" id="product-id" value="">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" id="product-name" required class="filter-select" style="width:100%">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="product-desc" rows="3" class="filter-select" style="width:100%"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" id="product-price" step="0.01" required class="filter-select" style="width:100%">
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" id="product-stock" required class="filter-select" style="width:100%">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select id="product-category" class="filter-select" style="width:100%">
                            <option value="Electronics">Electronics</option>
                            <option value="Clothing">Clothing</option>
                            <option value="Home">Home & Garden</option>
                            <option value="Books">Books</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="product-status" class="filter-select" style="width:100%">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="text" id="product-image" class="filter-select" style="width:100%" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Product</button>
                        <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let currentProducts = <?php echo json_encode($products); ?>;
        
        function openProductModal(productId = null) {
            if (productId) {
                const product = currentProducts.find(p => p.id == productId);
                if (product) {
                    document.getElementById('modal-title').textContent = 'Edit Product';
                    document.getElementById('product-id').value = product.id;
                    document.getElementById('product-name').value = product.name;
                    document.getElementById('product-desc').value = product.description || '';
                    document.getElementById('product-price').value = product.price;
                    document.getElementById('product-stock').value = product.stock;
                    document.getElementById('product-category').value = product.category || 'Other';
                    document.getElementById('product-status').value = product.status;
                    document.getElementById('product-image').value = product.image_url || '';
                }
            } else {
                document.getElementById('modal-title').textContent = 'Add Product';
                document.getElementById('product-form').reset();
                document.getElementById('product-id').value = '';
            }
            document.getElementById('product-modal').classList.remove('hidden');
        }
        
        function closeProductModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('product-modal').classList.add('hidden');
        }
        
        function saveProduct(event) {
            event.preventDefault();
            
            const productData = {
                id: document.getElementById('product-id').value || null,
                name: document.getElementById('product-name').value,
                description: document.getElementById('product-desc').value,
                price: parseFloat(document.getElementById('product-price').value),
                stock: parseInt(document.getElementById('product-stock').value),
                category: document.getElementById('product-category').value,
                status: document.getElementById('product-status').value,
                image_url: document.getElementById('product-image').value
            };
            
            fetch('api/save_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(productData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving product');
            });
        }
        
        function editProduct(id) {
            openProductModal(id);
        }
        
        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                fetch('api/delete_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
    </script>
</body>
</html>