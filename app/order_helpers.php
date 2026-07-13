<?php

declare(strict_types=1);

function generateOrderNo(PDO $db): string
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE order_no = :order_no');
    do {
        $timestamp = substr((string)time(), -10);
        $random = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $orderNo = $timestamp . $random;
        $stmt->execute([':order_no' => $orderNo]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $orderNo;
}

function generateStudentNo(PDO $db): string
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM students WHERE student_no = :student_no');
    do {
        $timestamp = substr((string)time(), -8);
        $random = str_pad((string)random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $studentNo = $timestamp . $random;
        $stmt->execute([':student_no' => $studentNo]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $studentNo;
}
