<?php
/**
 * Header template for Mining Buddy
 * 
 * This file contains the header layout including navigation
 */

// Include functions if not already included
require_once __DIR__ . '/functions.php';

// Initialize the application
initApp();

// Get current user if logged in
$currentUser = getCurrentUser();
$inOperation = isInActiveOperation();
$operation = $inOperation ? getCurrentOperation() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Mining Buddy</title>
    
    <!-- Bootstrap CSS (dark theme) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-dark-5@1.1.3/dist/css/bootstrap-night.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    
    <?php if (isset($extraStyles)): ?>
        <?= $extraStyles ?>
    <?php endif; ?>
</head>
<body class="bg-dark text-light">
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
            <div class="container">
                <a class="navbar-brand" href="<?= APP_URL ?>">
                    <!-- EVE API Doesn't provide a Mining icon, so we'll just use text -->
                    <i class="fas fa-gem text-primary me-2"></i>
                    Mining Buddy
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= APP_URL ?>">Home</a>
                        </li>
                        
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
                            </li>
                            
                            <?php if ($inOperation): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= APP_URL ?>/operation.php">
                                        <i class="fas fa-rocket text-warning"></i> Current Operation
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                    <?php if ($currentUser && !empty($currentUser['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($currentUser['avatar_url']) ?>"
                                             alt="<?= htmlspecialchars($currentUser['character_name'] ?? 'Unknown') ?>"
                                             class="rounded-circle me-1"
                                             style="width: 24px; height: 24px;">
                                    <?php endif; ?>
                                    <?= $currentUser ? htmlspecialchars($currentUser['character_name']) : 'Unknown User' ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end bg-dark border-secondary">
                                    <div class="dropdown-item text-light">
                                        <small class="text-muted">Corporation:</small><br>
                                        <?= $currentUser ? htmlspecialchars($currentUser['corporation_name'] ?? 'Unknown') : 'Unknown Corporation' ?>
                                    </div>
                                    <div class="dropdown-divider border-secondary"></div>
                                    <a class="dropdown-item text-light" href="<?= APP_URL ?>/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                    </a>
                                    <?php if ($inOperation): ?>
                                        <a class="dropdown-item text-light" href="<?= APP_URL ?>/operation.php">
                                            <i class="fas fa-rocket me-2 text-warning"></i> Current Operation
                                        </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider border-secondary"></div>
                                    <a class="dropdown-item text-light" href="<?= APP_URL ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a>
                                </div>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link btn btn-outline-primary" href="<?= APP_URL ?>/login.php">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login with EVE Online
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Flash Messages -->
        <?php 
        $flashMessage = getFlashMessage(); 
        if ($flashMessage): 
            $alertClass = match($flashMessage['type']) {
                'success' => 'alert-success',
                'error' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info'
            };
        ?>
            <div class="container mt-3">
                <div class="alert <?= $alertClass ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flashMessage['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="container py-4 flex-grow-1">