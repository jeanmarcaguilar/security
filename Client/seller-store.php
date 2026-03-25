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
    <link rel="stylesheet" href="style.css">
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
    
    <!-- PRODUCT MODAL -->
<div id="product-modal" class="fp-overlay hidden" onclick="closeProductModal(event)">
  <div class="fp-card product-modal-card" style="max-width:560px;">
    <button class="fp-close-btn" onclick="closeProductModal()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <h3 id="product-modal-title" style="font-family:var(--display);font-size:1.55rem;letter-spacing:1px;margin-bottom:1.5rem;">Add New Product</h3>
    <input type="hidden" id="product-edit-id"/>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group" style="grid-column:1/-1"><label>Product Name *</label><input type="text" id="p-name" placeholder="e.g. Wireless Headphones"/></div>
      <div class="form-group" style="grid-column:1/-1"><label>Description</label><textarea id="p-desc" rows="3" placeholder="Describe your product…" style="font-family:var(--font);font-size:.85rem;background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-sm);color:var(--text);padding:.6rem .85rem;width:100%;resize:vertical;"></textarea></div>
      <div class="form-group"><label>Price (₱) *</label><input type="number" id="p-price" placeholder="0.00" min="0" step="0.01"/></div>
      <div class="form-group"><label>Stock Quantity *</label><input type="number" id="p-stock" placeholder="0" min="0"/></div>
      <div class="form-group"><label>Category</label>
        <select id="p-category" style="font-family:var(--font);font-size:.85rem;background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-sm);color:var(--text);padding:.6rem .85rem;width:100%;">
          <option value="Electronics">Electronics</option>
          <option value="Clothing">Clothing</option>
          <option value="Home &amp; Garden">Home &amp; Garden</option>
          <option value="Sports">Sports</option>
          <option value="Books">Books</option>
          <option value="Food &amp; Beverage">Food &amp; Beverage</option>
          <option value="Health &amp; Beauty">Health &amp; Beauty</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group"><label>Status</label>
        <select id="p-status" style="font-family:var(--font);font-size:.85rem;background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-sm);color:var(--text);padding:.6rem .85rem;width:100%;">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label>Product Image URL</label><input type="text" id="p-image" placeholder="https://… (leave blank for default)"/></div>
    </div>
    <div id="product-modal-error" class="form-error" style="display:none;margin-bottom:.75rem;"></div>
    <div style="display:flex;gap:.75rem;margin-top:.5rem;">
      <button class="btn btn-primary" style="flex:1" onclick="saveProduct()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Product
      </button>
      <button class="btn btn-outline" onclick="closeProductModal()">Cancel</button>
    </div>
  </div>
</div>
    
    <script>
        let currentProducts = <?php echo json_encode($products); ?>;
        
        function openProductModal(productId = null) {
            if (productId) {
                const product = currentProducts.find(p => p.id == productId);
                if (product) {
                    document.getElementById('product-modal-title').textContent = 'Edit Product';
                    document.getElementById('product-edit-id').value = product.id;
                    document.getElementById('p-name').value = product.name;
                    document.getElementById('p-desc').value = product.description || '';
                    document.getElementById('p-price').value = product.price;
                    document.getElementById('p-stock').value = product.stock;
                    document.getElementById('p-category').value = product.category || 'Other';
                    document.getElementById('p-status').value = product.status;
                    document.getElementById('p-image').value = product.image_url || '';
                }
            } else {
                document.getElementById('product-modal-title').textContent = 'Add New Product';
                document.getElementById('product-edit-id').value = '';
                document.getElementById('p-name').value = '';
                document.getElementById('p-desc').value = '';
                document.getElementById('p-price').value = '';
                document.getElementById('p-stock').value = '';
                document.getElementById('p-category').value = 'Other';
                document.getElementById('p-status').value = 'active';
                document.getElementById('p-image').value = '';
            }
            document.getElementById('product-modal').classList.remove('hidden');
        }
        
        function closeProductModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('product-modal').classList.add('hidden');
        }
        
        function saveProduct() {
            const productId = document.getElementById('product-edit-id').value;
            const productData = {
                id: productId || null,
                name: document.getElementById('p-name').value,
                description: document.getElementById('p-desc').value,
                price: parseFloat(document.getElementById('p-price').value),
                stock: parseInt(document.getElementById('p-stock').value),
                category: document.getElementById('p-category').value,
                status: document.getElementById('p-status').value,
                image_url: document.getElementById('p-image').value
            };
            
            // Call the API from index.php
            fetch('../index.php?action=save_product', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(productData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    document.getElementById('product-modal-error').textContent = data.error || 'Error saving product';
                    document.getElementById('product-modal-error').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('product-modal-error').textContent = 'Connection error';
                document.getElementById('product-modal-error').style.display = 'block';
            });
        }
        
        function editProduct(id) {
            openProductModal(id);
        }
        
        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                fetch('../index.php?action=delete_product', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
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