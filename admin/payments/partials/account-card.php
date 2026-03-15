<div class="col-md-6 col-xl-4 mb-4">
    <div class="account-card <?php echo $account['is_default'] ? 'default' : ''; ?>">
        <?php if ($account['is_default']): ?>
            <span class="default-badge"><i class="fas fa-star me-1"></i>Default</span>
        <?php endif; ?>
        
        <span class="status-badge <?php echo $account['is_active'] ? 'active' : 'inactive'; ?>">
            <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
            <?php echo $account['is_active'] ? 'Active' : 'Inactive'; ?>
        </span>
        
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <div class="payment-icon icon-<?php echo $account['account_type']; ?> me-3">
                    <i class="<?php echo $account_types[$account['account_type']]['icon']; ?>"></i>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo htmlspecialchars($account['account_name']); ?></h6>
                    <small class="text-muted">ID: #<?php echo $account['id']; ?></small>
                </div>
            </div>
            
            <div class="account-details mb-3">
                <?php if ($account['account_email']): ?>
                    <div class="mb-1">
                        <i class="fas fa-envelope me-2 text-muted" style="width: 20px;"></i>
                        <small><?php echo htmlspecialchars($account['account_email']); ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ($account['phone_number']): ?>
                    <div class="mb-1">
                        <i class="fas fa-phone me-2 text-muted" style="width: 20px;"></i>
                        <small><?php echo htmlspecialchars($account['phone_number']); ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ($account['account_number']): ?>
                    <div class="mb-1">
                        <i class="fas fa-credit-card me-2 text-muted" style="width: 20px;"></i>
                        <small>**** <?php echo substr($account['account_number'], -4); ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ($account['bank_name']): ?>
                    <div class="mb-1">
                        <i class="fas fa-university me-2 text-muted" style="width: 20px;"></i>
                        <small><?php echo htmlspecialchars($account['bank_name']); ?></small>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="balance-section text-center py-2 mb-3 bg-light rounded">
                <div class="balance">$<?php echo number_format($account['current_balance'], 2); ?></div>
                <small class="text-muted">Current Balance</small>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('d M Y', strtotime($account['created_at'])); ?>
                    </small>
                </div>
                <div class="btn-group">
                    <a href="accounts.php?type=<?php echo $account['account_type']; ?>&id=<?php echo $account['id']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>