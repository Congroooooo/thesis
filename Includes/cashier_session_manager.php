<?php
/**
 * Cashier Session Manager - Helper functions for daily cashier assignments
 */

function getTodayCashier($conn) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("SELECT cashier_name FROM cashier_sessions WHERE date = ?");
        $stmt->execute([$today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['cashier_name'] : null;
    } catch (PDOException $e) {
        error_log("Error getting today's cashier: " . $e->getMessage());
        return null;
    }
}

function setTodayCashier($conn, $cashier_name, $user_id) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare(
            "INSERT INTO cashier_sessions (date, cashier_name, set_by_user_id, set_at) 
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                cashier_name = VALUES(cashier_name),
                updated_by_user_id = VALUES(set_by_user_id),
                updated_at = NOW()"
        );
        return $stmt->execute([$today, $cashier_name, $user_id]);
    } catch (PDOException $e) {
        error_log("Error setting today's cashier: " . $e->getMessage());
        return false;
    }
}

function getCashierByDate($conn, $date) {
    try {
        $stmt = $conn->prepare("SELECT cashier_name FROM cashier_sessions WHERE date = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['cashier_name'] : 'Cashier';
    } catch (PDOException $e) {
        error_log("Error getting cashier by date: " . $e->getMessage());
        return 'Cashier';
    }
}

function isCashierSetToday($conn) {
    return getTodayCashier($conn) !== null;
}

function getTodayCashierDetails($conn) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT cs.*, 
                   a.first_name, 
                   a.last_name 
            FROM cashier_sessions cs
            LEFT JOIN account a ON cs.set_by_user_id = a.id
            WHERE cs.date = ?
        ");
        $stmt->execute([$today]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting cashier details: " . $e->getMessage());
        return null;
    }
}

function getCashierHistory($conn, $start_date, $end_date) {
    try {
        $stmt = $conn->prepare("
            SELECT cs.*, 
                   a.first_name, 
                   a.last_name 
            FROM cashier_sessions cs
            LEFT JOIN account a ON cs.set_by_user_id = a.id
            WHERE cs.date BETWEEN ? AND ?
            ORDER BY cs.date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting cashier history: " . $e->getMessage());
        return [];
    }
}
