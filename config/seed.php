<?php
/**
 * seed.php  —  demo data inserted on the first run only.
 * (The admin account is created separately in db.php.)
 * Password for every demo student is: pass123
 */
function seed_sample_data($conn)
{
    $hash = password_hash('pass123', PASSWORD_DEFAULT);

    // name,          email,               campus spot
    $users = [
        ['Jhon Doe',   'jhon@woxsen.edu',   'Rise'],
        ['Aarav Singh','aarav@woxsen.edu',  'Library'],
        ['Ishita Rao', 'ishita@woxsen.edu', 'iCreate'],
        ['Riya Kapoor','riya@outlook.com',  'Pool area'],   // pairs with Microsoft sign-in demo
    ];
    foreach ($users as $u) {
        $n = mysqli_real_escape_string($conn, $u[0]);
        $e = mysqli_real_escape_string($conn, $u[1]);
        $h = mysqli_real_escape_string($conn, $u[2]);
        mysqli_query($conn, "INSERT INTO users (name, email, password, hostel, email_verified, verified)
                             VALUES ('$n', '$e', '$hash', '$h', 1, 1)");
    }

    // The demo users get ids 2..5 (id 1 is the admin created in db.php).
    // user_id, title, category, custom, price, condition, pickup, description, cod, upi
    $listings = [
        [2, 'Sony WH-1000XM4 Headphones', 'Electronic', '', 6500, 'Like new', 'Library',
            'Noise-cancelling over-ear headphones, barely used. Comes with the case and cable.', 1, 1],
        [3, 'Homemade Brownie Box (6)', 'Food', '', 250, 'Fresh', 'Rise',
            'Fudgy brownies baked tonight, box of 6. Pickup from Rise this evening.', 1, 1],
        [4, 'Minimalist Poster Set (3)', 'Posters/Decor', '', 400, 'Like new', 'iCreate',
            'Three A2 matte posters for your hostel wall. Rolled, no creases.', 1, 1],
        [5, 'Woxsen Fest Pass ×2', 'Tickets', '', 900, 'Like new', 'Pool area',
            'Two fest passes, selling together. Transfer at pickup.', 0, 1],
        [2, 'Casio FX-991EX Calculator', 'Electronic', '', 650, 'Good', 'Library',
            'Exam-allowed scientific calculator. Works perfectly.', 1, 1],
        [3, 'Skateboard (maple deck)', 'Other', 'Sports gear', 1800, 'Good', 'Fountain',
            'Complete skateboard, maple deck, decent wheels. Learning to long-board instead.', 1, 1],
    ];
    foreach ($listings as $l) {
        $uid = (int)$l[0];
        $t   = mysqli_real_escape_string($conn, $l[1]);
        $cat = mysqli_real_escape_string($conn, $l[2]);
        $cc  = mysqli_real_escape_string($conn, $l[3]);
        $pr  = (int)$l[4];
        $cn  = mysqli_real_escape_string($conn, $l[5]);
        $pk  = mysqli_real_escape_string($conn, $l[6]);
        $de  = mysqli_real_escape_string($conn, $l[7]);
        $cod = (int)$l[8];
        $upi = (int)$l[9];
        mysqli_query($conn, "INSERT INTO listings
            (user_id, title, category, custom_category, price, item_condition, pickup, description, pay_cod, pay_upi, status, avail)
            VALUES ($uid, '$t', '$cat', '$cc', $pr, '$cn', '$pk', '$de', $cod, $upi, 'approved', 'available')");
    }
}
?>
