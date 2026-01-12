<?php
session_start();

/* ================= DATABASE ================= */
$pdo = new PDO(
 "mysql:host=sql308.infinityfree.com;dbname=if0_38910344_transgo;charset=utf8mb4",
 "if0_38910344",
 "cXnGpYnW16"
);

/* ================== DISTANCE MATRIX (KM) FOR SA CITIES ================== */
$distances = [
    'Johannesburg'=>['Pretoria'=>60,'Cape Town'=>1400,'Durban'=>600,'Port Elizabeth'=>1100,'Bloemfontein'=>400,'East London'=>900,'Nelspruit'=>350,'Kimberley'=>650],
    'Pretoria'=>['Johannesburg'=>60,'Cape Town'=>1380,'Durban'=>580,'Port Elizabeth'=>1080,'Bloemfontein'=>380,'East London'=>880,'Nelspruit'=>330,'Kimberley'=>630],
    'Cape Town'=>['Johannesburg'=>1400,'Pretoria'=>1380,'Durban'=>1600,'Port Elizabeth'=>750,'Bloemfontein'=>1000,'East London'=>1100,'Nelspruit'=>1600,'Kimberley'=>900],
    'Durban'=>['Johannesburg'=>600,'Pretoria'=>580,'Cape Town'=>1600,'Port Elizabeth'=>1000,'Bloemfontein'=>800,'East London'=>600,'Nelspruit'=>550,'Kimberley'=>900],
    'Port Elizabeth'=>['Johannesburg'=>1100,'Pretoria'=>1080,'Cape Town'=>750,'Durban'=>1000,'Bloemfontein'=>550,'East London'=>300,'Nelspruit'=>1100,'Kimberley'=>450],
    'Bloemfontein'=>['Johannesburg'=>400,'Pretoria'=>380,'Cape Town'=>1000,'Durban'=>800,'Port Elizabeth'=>550,'East London'=>650,'Nelspruit'=>650,'Kimberley'=>250],
    'East London'=>['Johannesburg'=>900,'Pretoria'=>880,'Cape Town'=>1100,'Durban'=>600,'Port Elizabeth'=>300,'Bloemfontein'=>650,'Nelspruit'=>900,'Kimberley'=>650],
    'Nelspruit'=>['Johannesburg'=>350,'Pretoria'=>330,'Cape Town'=>1600,'Durban'=>550,'Port Elizabeth'=>1100,'Bloemfontein'=>650,'East London'=>900,'Kimberley'=>900],
    'Kimberley'=>['Johannesburg'=>650,'Pretoria'=>630,'Cape Town'=>900,'Durban'=>900,'Port Elizabeth'=>450,'Bloemfontein'=>250,'East London'=>650,'Nelspruit'=>900],
];

/* Fuel Rates per km (ZAR) */
$fuel_rates = ['Petrol'=>2.5,'Diesel'=>2.2];

/* ================= ACTION HANDLER ================= */
if(isset($_GET['action'])){

/* REGISTER */
if($_GET['action']=="register"){
$pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)")
->execute([
$_POST['name'], $_POST['email'], password_hash($_POST['password'],PASSWORD_DEFAULT), 'user'
]);
header("Location: index.php?page=login"); exit;
}

/* LOGIN */
if($_GET['action']=="login"){
$stmt=$pdo->prepare("SELECT * FROM users WHERE email=?");
$stmt->execute([$_POST['email']]);
$u=$stmt->fetch();
if($u && password_verify($_POST['password'],$u['password'])){
$_SESSION['uid']=$u['id'];
$_SESSION['role']=$u['role'];
header("Location: index.php?page=panel"); exit;
}
}

/* LOGOUT */
if($_GET['action']=="logout"){ session_destroy(); header("Location: index.php"); exit; }

/* BOOK (GUEST OR USER) */
if($_GET['action']=="book"){
$uid = $_SESSION['uid'] ?? 0;

$pickup = $_POST['pickup'];
$destination = $_POST['destination'];
$fuel = $_POST['fuel_type'];

// Calculate distance
$distance_km = $distances[$pickup][$destination] ?? 100;

// Calculate price
$price = $distance_km * $fuel_rates[$fuel];

// Insert booking
$pdo->prepare("INSERT INTO bookings
(user_id,guest_name,guest_phone,pickup,destination,travel_time,passengers,vehicle_id,price,status)
VALUES(?,?,?,?,?,?,?,?,?,'Pending')")
->execute([
$uid,
$_POST['guest_name'],
$_POST['guest_phone'],
$pickup,
$destination,
$_POST['time'],
$_POST['passengers'],
$_POST['vehicle'],
$price
]);

if(!$uid){ $_SESSION['guest_phone']=$_POST['guest_phone']; }

header("Location: index.php?page=panel"); exit;
}

/* ADMIN UPDATE */
if($_GET['action']=="adminUpdate"){
$pdo->prepare("UPDATE bookings SET eta=?, counter_price=?, driver_id=?, feedback_user=?, feedback_driver=? WHERE id=?")
->execute([$_POST['eta'],$_POST['counter_price'],$_POST['driver_id'],$_POST['feedback_user'],$_POST['feedback_driver'],$_GET['id']]);
header("Location: index.php?page=panel"); exit;
}

/* ADMIN APPROVE / REJECT */
if($_GET['action']=="approveRide"){
$pdo->prepare("UPDATE bookings SET status='Approved', approved_by_admin=1 WHERE id=?")
->execute([$_GET['id']]);
header("Location: index.php?page=panel"); exit;
}

if($_GET['action']=="rejectRide"){
$pdo->prepare("UPDATE bookings SET status='Rejected' WHERE id=?")
->execute([$_GET['id']]);
header("Location: index.php?page=panel"); exit;
}

/* USER APPROVES COUNTER PRICE */
if($_GET['action']=="approvePrice"){
$pdo->prepare("UPDATE bookings SET approved_by_user=1, price=counter_price WHERE id=?")
->execute([$_GET['id']]);
header("Location: index.php?page=panel"); exit;
}

/* DRIVER ACTIONS */
if($_GET['action']=="driverAccept"){
$pdo->prepare("UPDATE bookings SET status='Accepted', driver_id=? WHERE id=?")
->execute([$_SESSION['uid'], $_GET['id']]);
header("Location: index.php?page=panel"); exit;
}
if($_GET['action']=="driverOnRoute"){
$pdo->prepare("UPDATE bookings SET status='On Route' WHERE id=?")
->execute([$_GET['id']]);
header("Location: index.php?page=panel"); exit;
}
if($_GET['action']=="driverComplete"){
$pdo->prepare("UPDATE bookings SET status='Completed' WHERE id=?")
->execute([$_GET['id']]);
header("Location: index.php?page=panel"); exit;
}

}

$page=$_GET['page'] ?? 'home';
?>

<!DOCTYPE html>
<html>
<head>
<title>TransGo</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;font-family:Arial;background:radial-gradient(circle at top,#14001f,#050008);color:white}
.container{max-width:480px;margin:20px auto;background:#0c0014;padding:25px;border-radius:15px;box-shadow:0 0 20px rgba(128,0,255,.6)}
h1,h2{text-align:center;color:gold;text-shadow:0 0 10px purple}
input,select,button,textarea{width:100%;padding:12px;margin-top:10px;border-radius:10px;border:none;background:#1a0029;color:white}
button{background:linear-gradient(45deg,purple,gold);color:black;font-weight:bold}
a{color:gold;text-decoration:none}
.nav{text-align:center;margin-bottom:15px}
.card{padding:12px;margin-top:10px;border-bottom:1px solid #333}
.small{font-size:13px;color:#ccc}
</style>
</head>
<body>

<div class="container">

<?php if($page=="home"): ?>
<h1>🚖 TransGo Booking</h1>
<div class="nav"><a href="?page=login">Login</a> | <a href="?page=register">Register</a></div>

<form method="POST" action="?action=book">
<input name="guest_name" placeholder="Your Name (optional)">
<input name="guest_phone" placeholder="Phone Number (important)" required>
<select name="pickup" required>
<?php foreach(array_keys($distances) as $city){ echo "<option>$city</option>"; } ?>
</select>
<select name="destination" required>
<?php foreach(array_keys($distances) as $city){ echo "<option>$city</option>"; } ?>
</select>
<select name="fuel_type" required>
  <option>Petrol</option>
  <option>Diesel</option>
</select>
<input type="datetime-local" name="time" required>
<select name="passengers"><option>1</option><option>2</option><option>3</option></select>
<select name="vehicle"><option value="1">Taxi</option><option value="2">Minibus</option><option value="3">Luxury</option></select>
<button>Book Ride</button>
</form>
<?php endif; ?>

<?php if($page=="register"): ?>
<h2>Register</h2>
<form method="POST" action="?action=register">
<input name="name" placeholder="Name" required>
<input name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button>Create Account</button>
</form>
<?php endif; ?>

<?php if($page=="login"): ?>
<h2>Login</h2>
<form method="POST" action="?action=login">
<input name="email" placeholder="Email">
<input type="password" name="password" placeholder="Password">
<button>Login</button>
</form>
<?php endif; ?>

<?php if($page=="panel"): ?>
<h2><?= strtoupper($_SESSION['role'] ?? 'USER') ?> PANEL</h2>

<?php
/* ================== USER/GUEST VIEW ================== */
if(!isset($_SESSION['role']) || $_SESSION['role']=="user"){
$where = isset($_SESSION['uid']) ? "user_id=".$_SESSION['uid'] : "guest_phone='".($_SESSION['guest_phone'] ?? '')."'";
$rows=$pdo->query("SELECT * FROM bookings WHERE $where ORDER BY id DESC")->fetchAll();
foreach($rows as $r){
echo "<div class='card'>
<b>{$r['pickup']} ➝ {$r['destination']}</b><br>
Status: {$r['status']}<br>
ETA: ".($r['eta']?$r['eta']." mins":"Pending")."<br>
Driver: ".($r['driver_id']?"#".$r['driver_id']:"Not assigned")."<br>
Price: R{$r['price']}<br>";
if($r['counter_price'] && !$r['approved_by_user']){
echo "Counter Price: R{$r['counter_price']} <a href='?action=approvePrice&id={$r['id']}'>Approve</a><br>";
}
if($r['feedback_user']){
echo "<small>Admin Feedback: {$r['feedback_user']}</small>";
}
echo "</div>";
}
}

/* ================== DRIVER PANEL ================== */
if(isset($_SESSION['role']) && $_SESSION['role']=="driver"){
$uid = $_SESSION['uid'];
$rows=$pdo->query("SELECT * FROM bookings WHERE status='Approved' AND (driver_id IS NULL OR driver_id=$uid) ORDER BY id DESC")->fetchAll();
foreach($rows as $r){
echo "<div class='card'>
<b>{$r['pickup']} ➝ {$r['destination']}</b><br>
Status: {$r['status']}<br>
ETA: ".($r['eta']?$r['eta']." mins":"Pending")."<br>
Passenger: ".($r['guest_name'] ?? 'N/A')." | ".($r['guest_phone'] ?? 'N/A')."<br>";
if($r['driver_id']==$uid){
echo "<a href='?action=driverOnRoute&id={$r['id']}'>On Route</a> | ";
echo "<a href='?action=driverComplete&id={$r['id']}'>Complete</a>";
} else {
echo "<a href='?action=driverAccept&id={$r['id']}'>Accept Ride</a>";
}
if($r['feedback_driver']){
echo "<br><small>Admin Feedback: {$r['feedback_driver']}</small>";
}
echo "</div>";
}
}

/* ================== ADMIN PANEL ================== */
if(isset($_SESSION['role']) && $_SESSION['role']=="admin"){
$rows=$pdo->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll();
foreach($rows as $r){
echo "<div class='card'>
<b>{$r['pickup']} ➝ {$r['destination']}</b><br>
Status: {$r['status']}<br>
ETA: ".($r['eta']?$r['eta']." mins":"Pending")."<br>
User: ".($r['guest_name'] ?? 'N/A')." | ".($r['guest_phone'] ?? 'N/A')."<br>
Driver: ".($r['driver_id']?$r['driver_id']:"Not assigned")."<br>
Price: R{$r['price']}<br>
<form method='POST' action='?action=adminUpdate&id={$r['id']}'>
ETA (mins): <input name='eta' value='{$r['eta']}'>
Counter Price: <input name='counter_price' value='{$r['counter_price']}'>
Assign Driver (UserID): <input name='driver_id' value='{$r['driver_id']}'>
Admin Feedback to User: <textarea name='feedback_user'>{$r['feedback_user']}</textarea>
Admin Feedback to Driver: <textarea name='feedback_driver'>{$r['feedback_driver']}</textarea>
<button>Update</button>
</form>
<a href='?action=approveRide&id={$r['id']}'>Approve</a> |
<a href='?action=rejectRide&id={$r['id']}'>Reject</a>
</div>";
}
}
?>

<div class="nav"><a href="?action=logout">Logout</a></div>
<?php endif; ?>

</div>
</body>
</html>
