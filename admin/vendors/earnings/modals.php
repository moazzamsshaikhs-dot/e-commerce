<?php
// admin/vendors/earnings/modals.php
?>

<!-- Add Bank Account Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header bg-primary text-white border-0 rounded-top-20">
                <h5 class="modal-title"><i class="fas fa-university me-2"></i>Add Bank Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="action/add-bank-account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Holder Name *</label>
                            <input type="text" name="account_holder_name" class="form-control form-control-lg rounded-15" 
                                   value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Bank Name *</label>
                            <input type="text" name="bank_name" class="form-control form-control-lg rounded-15" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Number *</label>
                            <input type="text" name="account_number" class="form-control form-control-lg rounded-15" 
                                   pattern="\d{9,18}" title="9-18 digits" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">IFSC Code *</label>
                            <input type="text" name="ifsc_code" class="form-control form-control-lg rounded-15" 
                                   pattern="[A-Z]{4}0[A-Z0-9]{6}" title="Format: ABCD0123456" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Branch Name</label>
                            <input type="text" name="branch_name" class="form-control form-control-lg rounded-15">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Type</label>
                            <select name="account_type" class="form-select form-select-lg rounded-15">
                                <option value="savings">Savings</option>
                                <option value="current">Current</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SWIFT Code (Optional)</label>
                            <input type="text" name="swift_code" class="form-control form-control-lg rounded-15">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Routing Number (Optional)</label>
                            <input type="text" name="routing_number" class="form-control form-control-lg rounded-15">
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="modal_is_default" checked>
                                <label class="form-check-label" for="modal_is_default">
                                    Set as default account for withdrawals
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info bg-info bg-opacity-10 border-0 rounded-15 mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Your bank account will be verified within 24-48 hours. You can only withdraw to verified accounts.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-20">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gradient rounded-pill px-4">Add Bank Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add PayPal Modal -->
<div class="modal fade" id="addPayPalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header bg-info text-white border-0 rounded-top-20">
                <h5 class="modal-title"><i class="fab fa-paypal me-2"></i>Add PayPal Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="action/add-paypal-account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">PayPal Email *</label>
                        <input type="email" name="paypal_email" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">PayPal Account ID (Optional)</label>
                        <input type="text" name="paypal_account_id" class="form-control form-control-lg rounded-15">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="paypal_default" checked>
                        <label class="form-check-label" for="paypal_default">Set as default</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-20">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white rounded-pill px-4">Add PayPal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Stripe Modal -->
<div class="modal fade" id="addStripeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header" style="background: #6a1b9a; color: white; border: 0; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title"><i class="fab fa-stripe me-2"></i>Connect Stripe Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="action/add-stripe-account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Stripe Account ID *</label>
                        <input type="text" name="stripe_account_id" class="form-control form-control-lg rounded-15" placeholder="acct_..." required>
                        <small class="text-muted">Starts with 'acct_'</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Email *</label>
                        <input type="email" name="account_email" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Publishable Key (Optional)</label>
                        <input type="text" name="stripe_publishable_key" class="form-control form-control-lg rounded-15">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="stripe_default" checked>
                        <label class="form-check-label" for="stripe_default">Set as default</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-20">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white rounded-pill px-4" style="background: #6a1b9a;">Connect Stripe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Easypaisa Modal -->
<div class="modal fade" id="addEasypaisaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header bg-success text-white border-0 rounded-top-20">
                <h5 class="modal-title"><i class="fas fa-mobile-alt me-2"></i>Add Easypaisa Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="action/add-mobile-account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="mobile_type" value="easypaisa">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="mobile_number" class="form-control form-control-lg rounded-15" 
                               placeholder="03XXXXXXXXX" pattern="03\d{9}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">CNIC Number (Optional)</label>
                        <input type="text" name="cnic_number" class="form-control form-control-lg rounded-15" 
                               placeholder="12345-1234567-1">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="easypaisa_default" checked>
                        <label class="form-check-label" for="easypaisa_default">Set as default</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-20">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">Add Easypaisa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add JazzCash Modal -->
<div class="modal fade" id="addJazzCashModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header bg-danger text-white border-0 rounded-top-20">
                <h5 class="modal-title"><i class="fas fa-mobile-alt me-2"></i>Add JazzCash Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="action/add-mobile-account.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="mobile_type" value="jazzcash">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="mobile_number" class="form-control form-control-lg rounded-15" 
                               placeholder="03XXXXXXXXX" pattern="03\d{9}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">CNIC Number (Optional)</label>
                        <input type="text" name="cnic_number" class="form-control form-control-lg rounded-15" 
                               placeholder="12345-1234567-1">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="jazzcash_default" checked>
                        <label class="form-check-label" for="jazzcash_default">Set as default</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-20">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Add JazzCash</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Card Modal -->
<div class="modal fade" id="addCardModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header bg-warning text-white border-0 rounded-top-20">
                <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Add Credit/Debit Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="action/add-card.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Card Type *</label>
                        <select name="card_type" class="form-select form-select-lg rounded-15" required>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="amex">American Express</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Card Holder Name *</label>
                        <input type="text" name="card_holder_name" class="form-control form-control-lg rounded-15" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Card Number *</label>
                        <input type="text" name="card_number" class="form-control form-control-lg rounded-15 card-number" 
                               placeholder="1234 5678 9012 3456" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expiry Month *</label>
                            <select name="expiry_month" class="form-select form-select-lg rounded-15" required>
                                <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>">
                                    <?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expiry Year *</label>
                            <select name="expiry_year" class="form-select form-select-lg rounded-15" required>
                                <?php for($y=date('Y'); $y<=date('Y')+10; $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">CVV *</label>
                            <input type="text" name="cvv" class="form-control form-control-lg rounded-15" 
                                   pattern="\d{3,4}" maxlength="4" required>
                        </div>
                    </div>
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="card_default" checked>
                        <label class="form-check-label" for="card_default">Set as default</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-20">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white rounded-pill px-4">Add Card</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-20">
            <div class="modal-header bg-danger text-white border-0 rounded-top-20">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                <h5>Are you sure?</h5>
                <p class="text-muted">This action cannot be undone. This payment method will be permanently deleted.</p>
            </div>
            <div class="modal-footer bg-light border-0 rounded-bottom-20 justify-content-center">
                <form method="POST" id="deleteForm" action="action/delete-payment-method.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="type" id="delete_type">
                    <input type="hidden" name="id" id="delete_id">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>