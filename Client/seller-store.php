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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        switch ($action) {
            case 'add_product':
                $productData = json_decode($_POST['product_data'], true);
                if ($productData) {
                    $query = "INSERT INTO products (user_id, name, description, price, stock, category, status, image_url, created_at) 
                             VALUES (:user_id, :name, :description, :price, :stock, :category, :status, :image_url, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->bindParam(':name', $productData['name']);
                    $stmt->bindParam(':description', $productData['description']);
                    $stmt->bindParam(':price', $productData['price']);
                    $stmt->bindParam(':stock', $productData['stock']);
                    $stmt->bindParam(':category', $productData['category']);
                    $stmt->bindParam(':status', $productData['status']);
                    $stmt->bindParam(':image_url', $productData['image_url']);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Product added successfully'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to add product'];
                    }
                }
                break;
                
            case 'update_product':
                $productData = json_decode($_POST['product_data'], true);
                if ($productData && isset($productData['id'])) {
                    $query = "UPDATE products SET name = :name, description = :description, price = :price, 
                             stock = :stock, category = :category, status = :status, image_url = :image_url 
                             WHERE id = :id AND user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $productData['id']);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->bindParam(':name', $productData['name']);
                    $stmt->bindParam(':description', $productData['description']);
                    $stmt->bindParam(':price', $productData['price']);
                    $stmt->bindParam(':stock', $productData['stock']);
                    $stmt->bindParam(':category', $productData['category']);
                    $stmt->bindParam(':status', $productData['status']);
                    $stmt->bindParam(':image_url', $productData['image_url']);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Product updated successfully'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to update product'];
                    }
                }
                break;
                
            case 'delete_product':
                $productId = $_POST['product_id'] ?? 0;
                if ($productId) {
                    $query = "DELETE FROM products WHERE id = :id AND user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $productId);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Product deleted successfully'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to delete product'];
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

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
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Store — CyberShield</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap');
:root{--font:'Inter',sans-serif;--display:'Syne',sans-serif;--mono:'JetBrains Mono',monospace;--blue:#3B8BFF;--purple:#7B72F0;--teal:#00D4AA;--green:#10D982;--yellow:#F5B731;--orange:#FF8C42;--red:#FF3B5C;--t:.18s ease}
[data-theme=dark]{--bg:#030508;--bg2:#080d16;--bg3:#0d1421;--border:rgba(59,139,255,.08);--border2:rgba(255,255,255,.07);--text:#dde4f0;--muted:#4a6080;--muted2:#8898b4;--card-bg:#0a1020;--shadow:0 4px 24px rgba(0,0,0,.5)}
[data-theme=light]{--bg:#f0f4f8;--bg2:#e8eef5;--bg3:#fff;--border:rgba(59,139,255,.12);--border2:rgba(0,0,0,.1);--text:#0f172a;--muted:#94a3b8;--muted2:#475569;--card-bg:#fff;--shadow:0 4px 24px rgba(0,0,0,.1)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden}
body{font-family:var(--font);background:var(--bg);color:var(--text);transition:background .18s,color .18s}
.bg-grid{position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(59,139,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(59,139,255,.025) 1px,transparent 1px);background-size:40px 40px}
#app{display:flex;height:100vh;position:relative;z-index:1}
#sidebar{width:228px;min-width:228px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:width .18s,min-width .18s;overflow:hidden;z-index:10;flex-shrink:0}
#sidebar.collapsed{width:58px;min-width:58px}
.sb-brand{display:flex;align-items:center;gap:.75rem;padding:1rem .9rem .9rem;border-bottom:1px solid var(--border);flex-shrink:0}
.shield{width:34px;height:34px;background:linear-gradient(135deg,var(--blue),var(--purple));border-radius:9px;display:grid;place-items:center;flex-shrink:0;box-shadow:0 0 16px rgba(59,139,255,.3)}
.sb-brand-text{flex:1;overflow:hidden;white-space:nowrap}
.sb-brand-text h2{font-family:var(--display);font-size:.95rem;font-weight:700;letter-spacing:1px}
.sb-brand-text .badge{font-family:var(--mono);font-size:.55rem;letter-spacing:1.5px;text-transform:uppercase;background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.2);border-radius:4px;padding:.08rem .38rem;display:inline-block;margin-top:.1rem}
.sb-toggle{width:22px;height:22px;background:none;border:1px solid var(--border2);border-radius:5px;cursor:pointer;color:var(--muted2);display:grid;place-items:center;flex-shrink:0;transition:var(--t)}
.sb-toggle:hover{border-color:var(--blue);color:var(--text)}
#sidebar.collapsed .sb-toggle svg{transform:rotate(180deg)}
.sb-section{flex:1;overflow-y:auto;overflow-x:hidden;padding:.65rem 0}
.sb-section::-webkit-scrollbar{width:3px}.sb-section::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.sb-label{font-family:var(--mono);font-size:.55rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);padding:.5rem .9rem .25rem;white-space:nowrap;overflow:hidden}
#sidebar.collapsed .sb-label{opacity:0}
.sb-divider{height:1px;background:var(--border);margin:.5rem .9rem}
.sb-item{display:flex;align-items:center;gap:.65rem;padding:.52rem .9rem;cursor:pointer;color:var(--muted2);font-size:.82rem;font-weight:500;text-decoration:none;transition:var(--t);white-space:nowrap;overflow:hidden;position:relative}
.sb-item:hover{background:rgba(59,139,255,.07);color:var(--text)}
.sb-item.active{background:rgba(59,139,255,.1);color:var(--blue)}
.sb-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--blue);border-radius:0 3px 3px 0}
.sb-icon{display:flex;align-items:center;justify-content:center;width:18px;flex-shrink:0}
.sb-text{overflow:hidden}
#sidebar.collapsed .sb-text{display:none}
.sb-footer{border-top:1px solid var(--border);padding:.75rem .9rem;flex-shrink:0}
.sb-user{display:flex;align-items:center;gap:.65rem;overflow:hidden}
.sb-avatar{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--red),var(--orange));color:#fff;display:grid;place-items:center;font-size:.75rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
.sb-user-info{overflow:hidden;white-space:nowrap}
.sb-user-info p{font-size:.82rem;font-weight:600}
.sb-user-info span{font-size:.68rem;color:var(--muted2)}
#sidebar.collapsed .sb-user-info{display:none}
.btn-sb-logout{display:flex;align-items:center;gap:.35rem;margin-top:.65rem;width:100%;background:rgba(255,59,92,.08);border:1px solid rgba(255,59,92,.18);color:var(--red);font-family:var(--font);font-size:.75rem;font-weight:600;border-radius:7px;padding:.42rem .8rem;cursor:pointer;transition:var(--t)}
.btn-sb-logout:hover{background:rgba(255,59,92,.15)}
#sidebar.collapsed .btn-sb-logout span{display:none}
#main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.topbar{height:54px;min-height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;background:var(--bg2);border-bottom:1px solid var(--border);gap:1rem;flex-shrink:0}
.tb-bc{display:flex;align-items:center;gap:.4rem}
.tb-app{font-family:var(--mono);font-size:.68rem;color:var(--muted);letter-spacing:.5px}
.tb-title{font-family:var(--display);font-size:1.05rem;letter-spacing:1px}
.tb-sub{font-family:var(--mono);font-size:.63rem;letter-spacing:.5px;color:var(--muted);margin-top:1px}
.tb-right{display:flex;align-items:center;gap:.55rem}
.tb-search-wrap{position:relative}
.tb-search-icon{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:var(--muted2);pointer-events:none}
.tb-search{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:8px;padding:.38rem .8rem .38rem 2rem;font-family:var(--font);font-size:.78rem;color:var(--text);outline:none;width:200px;transition:var(--t)}
.tb-search:focus{border-color:rgba(59,139,255,.4)}
.tb-search::placeholder{color:var(--muted)}
.tb-date{font-family:var(--mono);font-size:.65rem;color:var(--muted2);white-space:nowrap}
.tb-divider{width:1px;height:20px;background:var(--border2);margin:0 .2rem}
.tb-icon-btn{width:32px;height:32px;border-radius:7px;border:1px solid var(--border2);background:rgba(255,255,255,.04);cursor:pointer;display:grid;place-items:center;color:var(--muted2);transition:var(--t);flex-shrink:0}
.tb-icon-btn:hover{border-color:var(--blue);color:var(--text)}
.tb-admin{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:9px;padding:.28rem .65rem .28rem .28rem;cursor:pointer;transition:var(--t)}
.tb-admin:hover{border-color:rgba(255,59,92,.28);background:rgba(255,59,92,.06)}
.tb-admin-av{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--red),var(--orange));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
.tb-admin-info{display:flex;flex-direction:column}
.tb-admin-name{font-size:.78rem;font-weight:600;line-height:1.2}
.tb-admin-role{font-size:.6rem;color:var(--red);letter-spacing:.5px;font-family:var(--mono)}
.notif-wrap{position:relative}
.notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--red);border:1.5px solid var(--bg2)}
.np{position:absolute;right:0;top:calc(100% + 8px);width:280px;background:var(--bg3);border:1px solid var(--border2);border-radius:10px;box-shadow:var(--shadow);z-index:100}
.np.hidden{display:none}
.np-hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--border)}
.np-hdr button{font-size:.72rem;color:var(--muted2);background:none;border:none;cursor:pointer}
.np-empty{font-size:.8rem;color:var(--muted2);padding:1rem;text-align:center}
.np-item{display:flex;gap:.6rem;padding:.7rem 1rem;border-bottom:1px solid var(--border)}
.np-item:last-child{border-bottom:none}
.np-dot{width:8px;height:8px;border-radius:50%;background:var(--red);flex-shrink:0;margin-top:4px}
.content{flex:1;overflow-y:auto;padding:1.5rem}
.content::-webkit-scrollbar{width:4px}.content::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);transition:border-color .18s}
.card:hover{border-color:var(--border2)}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:8px;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:var(--t);border:none;text-decoration:none}
.btn-p{background:var(--blue);color:#fff}.btn-p:hover{background:#2e7ae8}
.btn-s{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2)}.btn-s:hover{border-color:var(--blue);color:var(--text)}
.btn-d{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2)}.btn-d:hover{background:rgba(255,59,92,.2)}
.btn-sm{font-size:.72rem;padding:.32rem .7rem}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.65);display:grid;place-items:center;z-index:200;backdrop-filter:blur(6px);opacity:0;pointer-events:none;transition:opacity .25s ease}
.mo.active{opacity:1;pointer-events:all}
.mo .modal{background:var(--bg3);border:1px solid var(--border2);border-radius:16px;width:min(90vw,560px);box-shadow:0 24px 80px rgba(0,0,0,.7);transform:translateY(24px) scale(.97);opacity:0;transition:transform .28s cubic-bezier(.34,1.46,.64,1),opacity .22s ease}
.mo.active .modal{transform:none;opacity:1}
.mhdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid var(--border)}
.mhdr h3{font-family:var(--display);font-size:1rem;font-weight:700}
.mcl{width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.mcl:hover{border-color:var(--red);color:var(--red)}
.mbdy{padding:1.25rem}
/* Product modal overlay */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);display:grid;place-items:center;z-index:200;backdrop-filter:blur(6px);opacity:0;pointer-events:none;transition:opacity .25s ease}
.modal-overlay.active{opacity:1;pointer-events:all}
.modal-overlay.hidden{display:none}
.modal-overlay .modal-panel{background:var(--bg3);border:1px solid rgba(59,139,255,.15);border-radius:18px;width:min(92vw,520px);max-height:90vh;overflow-y:auto;box-shadow:0 32px 100px rgba(0,0,0,.75),0 0 0 1px rgba(255,255,255,.04);transform:translateY(28px) scale(.96);opacity:0;transition:transform .3s cubic-bezier(.34,1.42,.64,1),opacity .22s ease;scrollbar-width:thin;scrollbar-color:var(--border2) transparent}
.modal-overlay.active .modal-panel{transform:none;opacity:1}
.modal-panel-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.3rem 1.5rem 1rem;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--bg3);z-index:1;border-radius:18px 18px 0 0}
.modal-panel-hdr h3{font-family:var(--display);font-size:1.05rem;font-weight:700;letter-spacing:.5px;background:linear-gradient(90deg,var(--text),var(--muted2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.modal-panel-close{width:30px;height:30px;border-radius:8px;border:1px solid var(--border2);background:rgba(255,255,255,.03);color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t);flex-shrink:0}
.modal-panel-close:hover{border-color:rgba(255,59,92,.4);color:var(--red);background:rgba(255,59,92,.08)}
.modal-panel-body{padding:1.5rem}
.mpf-group{margin-bottom:1.1rem}
.mpf-group label{display:block;font-size:.75rem;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted2);margin-bottom:.45rem;font-family:var(--mono)}
.mpf-input{width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:9px;padding:.6rem .85rem;font-family:var(--font);font-size:.85rem;color:var(--text);outline:none;transition:border-color .18s,box-shadow .18s;-webkit-appearance:none}
.mpf-input:focus{border-color:rgba(59,139,255,.5);box-shadow:0 0 0 3px rgba(59,139,255,.1)}
.mpf-input::placeholder{color:var(--muted)}
textarea.mpf-input{resize:vertical;min-height:70px;line-height:1.5}
select.mpf-input option{background:var(--bg3);color:var(--text)}
.mpf-row{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
.mpf-actions{display:flex;gap:.65rem;margin-top:1.5rem;padding-top:1.1rem;border-top:1px solid var(--border)}
.mpf-save{flex:1;background:linear-gradient(135deg,var(--blue),var(--purple));color:#fff;border:none;border-radius:9px;padding:.65rem 1rem;font-family:var(--font);font-size:.85rem;font-weight:600;cursor:pointer;transition:opacity .18s,transform .18s;letter-spacing:.3px}
.mpf-save:hover{opacity:.9;transform:translateY(-1px)}
.mpf-save:disabled{opacity:.5;cursor:not-allowed;transform:none}
.mpf-cancel{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2);border-radius:9px;padding:.65rem 1.1rem;font-family:var(--font);font-size:.85rem;font-weight:500;cursor:pointer;transition:var(--t)}
.mpf-cancel:hover{border-color:var(--border2);color:var(--text);background:rgba(255,255,255,.08)}
#toast-c{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.5rem;z-index:300}
.toast{background:var(--bg3);border:1px solid var(--border2);border-radius:9px;padding:.75rem 1rem;font-size:.82rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.6rem;animation:sl .2s ease;min-width:240px}
@keyframes sl{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.ti{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* Enhanced Product Grid */
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
  border: 1px solid var(--border);
  transition: all 0.3s ease;
  position: relative;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
  border-color: var(--blue);
}

.product-image {
  height: 180px;
  background: linear-gradient(135deg, var(--navy-3), var(--navy-2));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  position: relative;
  overflow: hidden;
}

.product-image::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, transparent 30%, rgba(59, 139, 255, 0.1) 70%, transparent 30%);
  animation: shimmer 2s infinite;
}

@keyframes shimmer {
  0% { background-position: -200% center; }
  100% { background-position: 200% center; }
}

.product-info {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.product-name {
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text);
  margin-bottom: 0.5rem;
}

.product-price {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 0.5rem;
}

.product-stock {
  font-size: 0.9rem;
  color: var(--muted);
  margin-bottom: 0.5rem;
}

.product-status {
  display: inline-block;
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-active { 
  background: rgba(16, 217, 130, 0.2);
  color: #10D982;
}

.status-inactive { 
  background: rgba(239, 68, 68, 0.2);
  color: #EF4444;
}

.product-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}

.btn-xs {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
  font-weight: 600;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.btn-xs:hover {
  transform: translateY(-2px);
}

/* Enhanced Filter Section */
.filter-section {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.filter-header h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
}

.export-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  background: var(--blue);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.export-btn:hover {
  background: var(--purple);
  transform: translateY(-2px);
}

.filter-buttons.enhanced {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.filter-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  background: rgba(59, 139, 255, 0.05);
  border: 2px solid var(--border);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 600;
  color: var(--text);
  position: relative;
  overflow: hidden;
}

.filter-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, var(--blue), var(--purple));
  opacity: 0;
  transition: opacity 0.3s ease;
}

.filter-btn:hover {
  background: rgba(59, 139, 255, 0.1);
  border-color: var(--blue);
  transform: translateY(-2px);
}
    text-align: center;
  }

  .filter-btn:not(.active) .filter-count {
    background: var(--border);
    color: var(--muted);
  }

  /* Store-specific styles */
  .store-hero {
    display: flex;
    align-items: center;
    padding: 2.5rem;
    background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg2) 100%);
    border-radius: 24px;
    border: 1px solid var(--border);
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
    width: 100%;
  }

  .store-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 20% 80%, rgba(59, 139, 255, 0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(123, 114, 240, 0.1) 0%, transparent 50%);
    pointer-events: none;
  }

  .hero-content {
    flex: 1;
    z-index: 1;
  }

  .hero-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1.5rem;
  }

  .hero-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
  }

  .hero-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--blue);
    line-height: 1;
  }

  .hero-label {
    font-size: 0.85rem;
    color: var(--muted);
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .hero-trophy {
    position: relative;
    z-index: 1;
  }

  .trophy-container {
    position: relative;
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .trophy-icon {
    font-size: 4rem;
    z-index: 2;
    position: relative;
    animation: float 3s ease-in-out infinite;
  }

  .trophy-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.3) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
  }

  @keyframes float {
    0%, 100% {
      transform: translateY(0px);
    }
    50% {
      transform: translateY(-10px);
    }
  }

  @keyframes pulse {
    0%, 100% {
      opacity: 0.5;
      transform: translate(-50%, -50%) scale(1);
    }
    50% {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1.1);
    }
  }

  .stats-grid.enhanced {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
  }

  .stat-card.enhanced {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
  }

  .stat-card.enhanced:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    border-color: var(--blue);
  }

  .stat-card.enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--blue), var(--purple));
  }

  .stat-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 139, 255, 0.1);
    border-radius: 12px;
    flex-shrink: 0;
  }

  .stat-content {
    flex: 1;
  }

  .stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1;
  }

  .stat-label {
    font-size: 0.78rem;
    color: var(--muted);
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .filter-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
  }

  .filter-title {
    font-family: var(--display);
    font-size: 1.2rem;
    font-weight: 700;
    letter-spacing: 1px;
  }

  .filter-actions {
    display: flex;
    gap: 0.75rem;
  }

  .export-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--blue);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
  }

  .export-btn:hover {
    background: var(--purple);
    transform: translateY(-2px);
  }

  .filter-buttons.enhanced {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
  }

  .filter-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: rgba(59, 139, 255, 0.05);
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    color: var(--text);
    position: relative;
    overflow: hidden;
  }

  .filter-btn:hover {
    background: rgba(59, 139, 255, 0.1);
    border-color: var(--blue);
    transform: translateY(-2px);
  }

  .filter-btn.active {
    background: linear-gradient(135deg, var(--blue), var(--purple));
    color: white;
    border-color: transparent;
    box-shadow: 0 8px 24px rgba(59, 139, 255, 0.3);
  }

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
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    position: relative;
  }

  .product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    border-color: var(--blue);
  }

  .product-image {
    height: 180px;
    background: linear-gradient(135deg, var(--bg2), var(--bg3));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    position: relative;
    overflow: hidden;
  }

  .product-image::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(59, 139, 255, 0.1) 70%, transparent 30%);
    animation: shimmer 2s infinite;
  }

  @keyframes shimmer {
    0% {
      background-position: -200% center;
    }
    100% {
      background-position: 200% center;
    }
  }

  .product-info {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .product-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text);
    margin-bottom: 0.5rem;
  }

  .product-price {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--blue);
    margin-bottom: 0.5rem;
  }

  .product-stock {
    font-size: 0.9rem;
    color: var(--muted);
    margin-bottom: 0.5rem;
  }

  .product-status {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .status-active {
    background: rgba(16, 217, 130, 0.15);
    color: var(--green);
  }

  .status-inactive {
    background: rgba(239, 68, 68, 0.15);
    color: var(--red);
  }

  .product-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
  }

  .btn-xs {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s ease;
  }

  .btn-xs:hover {
    transform: translateY(-2px);
  }

  @media (max-width: 768px) {
    .store-hero {
      flex-direction: column;
      text-align: center;
      gap: 1.5rem;
    }

    .hero-stats {
      justify-content: center;
      gap: 1rem;
    }

    .hero-stat {
      padding: 0.75rem 1rem;
    }

    .trophy-container {
      width: 80px;
      height: 80px;
    }

    .trophy-icon {
      font-size: 3rem;
    }

    .stats-grid.enhanced {
      grid-template-columns: 1fr;
    }

    .filter-buttons.enhanced {
      grid-template-columns: 1fr;
    }

    .filter-header {
      flex-direction: column;
      gap: 1rem;
      align-items: stretch;
    }

    .product-grid {
  }
</style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <!-- SIDEBAR -->
  <div id="sidebar">
    <div class="sb-brand">
      <div class="shield">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div class="sb-brand-text">
        <h2>CyberShield</h2>
        <span class="badge">Client Portal</span>
      </div>
      <button class="sb-toggle" onclick="toggleSidebar()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Assessment</span></a>
      <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Results</span></a>
      <a class="sb-item" href="leaderboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Leaderboard</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Seller Hub</div>
      <a class="sb-item active" href="seller-store.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1 0 8 0"/></svg></span><span class="sb-text">My Store</span></a>
      <a class="sb-item" href="seller-analytics.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 20 22 20"/></svg></span><span class="sb-text">Analytics</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Account</div>
      <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="sb-text">My Profile</span></a>
      <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span><span class="sb-text">Security Tips</span></a>
      <a class="sb-item active" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Terms & Privacy</span></a>
    </div>
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?></div>
        <div class="sb-user-info">
          <p><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></p>
          <span>Vendor Account</span>
        </div>
      </div>
      <button class="btn-sb-logout" onclick="doLogout()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </div>

  <!-- MAIN -->
  <div id="main">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="tb-bc">
        <div>
          <div class="tb-title">My Store</div>
          <div class="tb-sub">Product Management & Inventory</div>
        </div>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <svg class="tb-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" class="tb-search" placeholder="Search products..."/>
        </div>
        <div class="tb-divider"></div>
        <div class="tb-date" id="tb-date"></div>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" id="tmoon" onclick="toggleTheme()" title="Toggle theme">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 21 12.79z"/></svg>
        </button>
        <button class="tb-icon-btn" id="tsun" onclick="toggleTheme()" title="Toggle theme" style="display:none">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="tb-divider"></div>
        <div class="notif-wrap">
          <button class="tb-icon-btn" onclick="toggleNotif()" title="Notifications">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot" id="notif-dot" style="display:none"></span>
          </button>
          <div class="np hidden" id="np">
            <div class="np-hdr"><span>Notifications</span><button onclick="clearNotifs()">Clear all</button></div>
            <div id="np-list"><p class="np-empty">No alerts</p></div>
          </div>
        </div>
        <div class="tb-divider"></div>
        <div class="tb-admin" onclick="window.location.href='profile.php'">
          <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?></div>
          <div class="tb-admin-info">
            <div class="tb-admin-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></div>
            <div class="tb-admin-role">Vendor</div>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4-4 4"/></svg>
        </div>
      </div>
    </div>

    <!-- CONTENT -->
    <div class="content">
      <div class="page-inner fade-in">
        
        <!-- Hero Section -->
        <div class="store-hero">
          <div class="hero-content" style="width:100%">
            <h1 style="margin-bottom: 0.5rem; font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, #3B8BFF, #7B72F0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">My Store</h1>
            <p style="font-size: 1.1rem; opacity: 0.9;">Manage your products and inventory efficiently</p>
            <div class="hero-stats" style="flex-wrap:wrap">
              <div class="hero-stat">
                <span class="hero-number"><?php echo count($products); ?></span>
                <span class="hero-label">Products</span>
              </div>
              <div class="hero-stat">
                <span class="hero-number">
                  <?php
                  $activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
                  echo $activeCount;
                  ?>
                </span>
                <span class="hero-label">Active</span>
              </div>
              <div class="hero-stat">
                <span class="hero-number">
                  <?php
                  $totalStock = array_sum(array_column($products, 'stock'));
                  echo number_format($totalStock, 0);
                  ?>
                </span>
                <span class="hero-label">Total Stock</span>
              </div>
              <div class="hero-stat">
                <span class="hero-number">
                  ₱<?php
                  $totalValue = array_sum(array_map(fn($p) => $p['price'] * $p['stock'], $products));
                  echo number_format($totalValue, 0);
                  ?>
                </span>
                <span class="hero-label">Inventory Value</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Enhanced Statistics -->
        <div class="stats-grid enhanced">
          <div class="stat-card enhanced">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
              <div class="stat-value"><?php echo count($products); ?></div>
              <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10D982" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
          </div>
          <div class="stat-card enhanced">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
              <div class="stat-value">
                <?php
                $activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
                echo $activeCount;
                ?>
              </div>
              <div class="stat-label">Active Listings</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10D982" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
          </div>
          <div class="stat-card enhanced">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
              <div class="stat-value">
                <?php
                $totalStock = array_sum(array_column($products, 'stock'));
                echo number_format($totalStock, 0);
                ?>
              </div>
              <div class="stat-label">Total Stock</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10D982" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
          </div>
          <div class="stat-card enhanced">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
              <div class="stat-value">
                ₱<?php
                $totalValue = array_sum(array_map(fn($p) => $p['price'] * $p['stock'], $products));
                echo number_format($totalValue, 0);
                ?>
              </div>
              <div class="stat-label">Inventory Value</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F5B731" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
            </div>
          </div>
        </div>
        
        <!-- Enhanced Actions Section -->
        <div class="filter-section">
          <div class="filter-header">
            <h3>Product Management</h3>
            <button class="export-btn" onclick="openProductModal()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M12 5v14"/><path d="M12 5v14"/><path d="M12 5v14"/><path d="M12 5v14"/><path d="M12 5v14"/></svg>
              Add New Product
            </button>
          </div>
          <div class="filter-buttons enhanced">
            <button class="filter-btn " onclick="filterProducts('all', this)">
              <span class="filter-icon">🌐</span>
              <span class="filter-text">All Products</span>
              <span class="filter-count"><?php echo count($products); ?></span>
            </button>
            <button class="filter-btn" onclick="filterProducts('active', this)">
              <span class="filter-icon">✅</span>
              <span class="filter-text">Active</span>
              <span class="filter-count"><?php echo count(array_filter($products, fn($p) => $p['status'] === 'active')); ?></span>
            </button>
            <button class="filter-btn" onclick="filterProducts('inactive', this)">
              <span class="filter-icon">⏸️</span>
              <span class="filter-text">Inactive</span>
              <span class="filter-count"><?php echo count(array_filter($products, fn($p) => $p['status'] === 'inactive')); ?></span>
            </button>
            <button class="filter-btn" onclick="filterProducts('lowstock', this)">
              <span class="filter-icon">⚠️</span>
              <span class="filter-text">Low Stock</span>
              <span class="filter-count"><?php echo count(array_filter($products, fn($p) => $p['stock'] < 10)); ?></span>
            </button>
          </div>
        </div>
        
        <!-- Product Grid -->
        <div class="product-grid" id="product-grid">
          <?php foreach ($products as $product): ?>
          <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
            <div class="product-image">
              <?php echo $product['image_url'] ? "<img src='{$product['image_url']}' style='width:100%;height:100%;object-fit:cover;'>" : '🛍️'; ?>
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

  <!-- Product Modal -->
  <div id="product-modal" class="modal-overlay" onclick="if(event.target===this)closeProductModal()">
    <div class="modal-panel" onclick="event.stopPropagation()">
      <div class="modal-panel-hdr">
        <h3 id="modal-title">Add Product</h3>
        <button class="modal-panel-close" onclick="closeProductModal()" title="Close">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-panel-body">
        <form id="product-form" onsubmit="saveProduct(event)">
          <input type="hidden" id="product-id" value="">
          <div class="mpf-group">
            <label>Product Name <span style="color:var(--red)">*</span></label>
            <input type="text" id="product-name" required class="mpf-input" placeholder="Enter product name">
          </div>
          <div class="mpf-group">
            <label>Description</label>
            <textarea id="product-desc" rows="3" class="mpf-input" placeholder="Optional product description…"></textarea>
          </div>
          <div class="mpf-row">
            <div class="mpf-group" style="margin-bottom:0">
              <label>Price (₱) <span style="color:var(--red)">*</span></label>
              <input type="number" id="product-price" step="0.01" min="0" required class="mpf-input" placeholder="0.00">
            </div>
            <div class="mpf-group" style="margin-bottom:0">
              <label>Stock Qty <span style="color:var(--red)">*</span></label>
              <input type="number" id="product-stock" min="0" required class="mpf-input" placeholder="0">
            </div>
          </div>
          <div class="mpf-row" style="margin-top:1.1rem">
            <div class="mpf-group" style="margin-bottom:0">
              <label>Category</label>
              <select id="product-category" class="mpf-input">
                <option value="Electronics">Electronics</option>
                <option value="Clothing">Clothing</option>
                <option value="Home">Home &amp; Garden</option>
                <option value="Books">Books</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="mpf-group" style="margin-bottom:0">
              <label>Status</label>
              <select id="product-status" class="mpf-input">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="mpf-group" style="margin-top:1.1rem">
            <label>Image URL</label>
            <input type="text" id="product-image" class="mpf-input" placeholder="https://...">
          </div>
          <div class="mpf-actions">
            <button type="submit" class="mpf-save" id="mpf-submit-btn">Save Product</button>
            <button type="button" class="mpf-cancel" onclick="closeProductModal()">Cancel</button>
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
            }
            const overlay = document.getElementById('product-modal');
            overlay.classList.remove('hidden');
            // Force reflow so transition fires
            overlay.offsetHeight;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeProductModal(event) {
            if (event && event.stopPropagation) event.stopPropagation();
            const overlay = document.getElementById('product-modal');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            // Hide after transition completes
            overlay.addEventListener('transitionend', function handler() {
                overlay.classList.add('hidden');
                overlay.removeEventListener('transitionend', handler);
            });
        }
        
        function saveProduct(event) {
            event.preventDefault();
            const productId = document.getElementById('product-id').value;
            const formData = {
                id: productId || null,
                name: document.getElementById('product-name').value,
                description: document.getElementById('product-desc').value,
                price: parseFloat(document.getElementById('product-price').value),
                stock: parseInt(document.getElementById('product-stock').value),
                category: document.getElementById('product-category').value,
                status: document.getElementById('product-status').value,
                image_url: document.getElementById('product-image').value
            };
            
            // Show loading state
            const submitBtn = document.getElementById('mpf-submit-btn') || event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;
            
            // Send AJAX request to save product
            fetch('seller-store.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    'action': productId ? 'update_product' : 'add_product',
                    'product_data': JSON.stringify(formData)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(productId ? 'Product updated successfully!' : 'Product added successfully!', 'green');
                    closeProductModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error: ' + data.message, 'red');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while saving the product', 'red');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function editProduct(productId) {
            const product = currentProducts.find(p => p.id == productId);
            if (product) {
                openProductModal(productId);
            } else {
                showToast('Product not found', 'red');
            }
        }
        
        function updateProductCounts() {
            // Update statistics after product operations
            const totalProductsEl = document.querySelector('.hero-stat .hero-number');
            if (totalProductsEl) {
                totalProductsEl.textContent = currentProducts.length;
            }
        }
        
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                // Send AJAX request to delete product
                fetch('seller-store.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        'action': 'delete_product',
                        'product_id': productId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Product deleted successfully!', 'green');
                        // Remove product from display without page reload
                        const productCard = document.querySelector(`[data-product-id="${productId}"]`);
                        if (productCard) {
                            productCard.style.opacity = '0';
                            productCard.style.transform = 'scale(0.8)';
                            setTimeout(() => productCard.remove(), 300);
                        }
                        // Update product count
                        currentProducts = currentProducts.filter(p => p.id != productId);
                        updateProductCounts();
                    } else {
                        showToast('Error: ' + data.message, 'red');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while deleting the product', 'red');
                });
            }
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
        
        function filterProducts(status, btn) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                // Add filtering logic here based on status
                if (status === 'all') {
                    card.style.display = '';
                } else if (status === 'active' || status === 'inactive') {
                    // Filter by product status
                    const productStatus = card.querySelector('.product-status').textContent.toLowerCase();
                    card.style.display = productStatus === status ? '' : 'none';
                } else if (status === 'lowstock') {
                    // Filter by low stock (less than 10)
                    const stockText = card.querySelector('.product-stock').textContent;
                    const stock = parseInt(stockText.match(/\d+/)[0]);
                    card.style.display = stock < 10 ? '' : 'none';
                }
            });
        }
        
        function isDark(){return document.documentElement.getAttribute('data-theme')==='dark'}
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');localStorage.setItem('cs_sb',document.getElementById('sidebar').classList.contains('collapsed')?'1':'0');}
        function toggleTheme(){const d=!isDark();document.documentElement.setAttribute('data-theme',d?'dark':'light');localStorage.setItem('cs_th',d?'dark':'light');const m=document.getElementById('tmoon'),s=document.getElementById('tsun');if(m)m.style.display=d?'':'none';if(s)s.style.display=d?'none':'';}
        function toggleNotif(){const p=document.getElementById('np');if(p)p.classList.toggle('hidden');}
        function clearNotifs(){const l=document.getElementById('np-list');if(l)l.innerHTML='<p class="np-empty">No alerts</p>';const d=document.getElementById('notif-dot');if(d)d.style.display='none';const p=document.getElementById('np');if(p)p.classList.add('hidden');}
        function showToast(msg,color='blue'){const cols={blue:'var(--blue)',green:'var(--green)',red:'var(--red)',yellow:'var(--yellow)'};const t=document.createElement('div');t.className='toast';t.innerHTML=`<span class="ti" style="background:${cols[color]||cols.blue}"></span><span>${msg}</span>`;document.getElementById('toast-c').appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300);},2500);}
        function doLogout() {
          const modal = document.getElementById('logout-modal');
          const modalTitle = document.getElementById('logout-modal-title');
          const modalBody = document.getElementById('logout-modal-body');
          
          modalTitle.textContent = 'Confirm Logout';
          modalBody.innerHTML = `
            <div style="text-align: center; padding: 1rem;">
              <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--red);">🚪</div>
              <h3 style="margin-bottom: 0.5rem; color: var(--text);">Are you sure you want to sign out?</h3>
              <p style="color: var(--muted2); margin-bottom: 1.5rem;">You will be redirected to the landing page.</p>
              <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <button class="btn btn-s" onclick="closeLogoutModal()" style="padding: 0.5rem 1.5rem;">Cancel</button>
                <button class="btn btn-d" onclick="confirmLogout()" style="padding: 0.5rem 1.5rem;">Sign Out</button>
              </div>
            </div>
          `;
          
          modal.classList.remove('hidden');
        }

        function confirmLogout() {
          window.location.href = '../landingpage.php';
        }
        function closeLogoutModal(){document.getElementById('logout-modal').classList.add('hidden')}
        function closeModal(){document.getElementById('modal-overlay').classList.add('hidden')}
        document.addEventListener('DOMContentLoaded',()=>{
          const th=localStorage.getItem('cs_th')||'dark';
          document.documentElement.setAttribute('data-theme',th);
          const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
          if(m)m.style.display=th==='dark'?'':'none';
          if(s)s.style.display=th==='dark'?'none':'';
          const sb=localStorage.getItem('cs_sb');
          if(sb==='1')document.getElementById('sidebar').classList.add('collapsed');
          const d=document.getElementById('tb-date');
          if(d)d.textContent=new Date().toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
          // Ensure product modal is hidden on load
          const pm = document.getElementById('product-modal');
          if(pm){pm.classList.add('hidden');pm.classList.remove('active');}
          // Escape key closes modal
          document.addEventListener('keydown', e => {
            if(e.key === 'Escape') {
              const mo = document.getElementById('product-modal');
              if(mo && mo.classList.contains('active')) closeProductModal();
              const moo = document.getElementById('modal-overlay');
              if(moo && !moo.classList.contains('hidden')) closeModal();
            }
          });
        });
    </script>

    <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
      <div class="modal">
        <div class="mhdr"><h3 id="modal-title">Confirm Action</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        <div class="mbdy" id="modal-body"></div>
      </div>
    </div>
    
    <!-- Logout Modal -->
    <div id="logout-modal" class="mo hidden" onclick="if(event.target===this)closeLogoutModal()">
      <div class="modal">
        <div class="mhdr"><h3 id="logout-modal-title">Confirm Logout</h3><button class="mcl" onclick="closeLogoutModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        <div class="mbdy" id="logout-modal-body"></div>
      </div>
    </div>
    
    <div id="toast-c"></div>
</body>
</html>