<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Report</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        h1 {
            color: #4361ee;
            border-bottom: 2px solid #4361ee;
            padding-bottom: 10px;
        }
        h2 {
            color: #2b2d42;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background: #4361ee;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .summary-box {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .summary-item {
            display: inline-block;
            margin-right: 40px;
        }
        .summary-item .label {
            font-size: 11px;
            opacity: 0.8;
        }
        .summary-item .value {
            font-size: 18px;
            font-weight: bold;
        }
        .status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-completed { background: #06d6a0; color: white; }
        .status-pending { background: #ffb703; color: white; }
        .status-failed { background: #ef476f; color: white; }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>
    <h1>Payment Report</h1>
    
    <p><strong>Report Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $report_type)); ?></p>
    <p><strong>Date Range:</strong> <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
    <p><strong>Generated:</strong> <?php echo date('d M Y H:i:s'); ?></p>
    
    <?php if ($include_summary && isset($data['summary'])): ?>
    <div class="summary-box">
        <div class="summary-item">
            <div class="label">Total Revenue</div>
            <div class="value">$<?php echo number_format($data['summary']['total_revenue'], 2); ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Total Orders</div>
            <div class="value"><?php echo number_format($data['summary']['total_orders']); ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Avg Order Value</div>
            <div class="value">$<?php echo number_format($data['summary']['avg_order_value'], 2); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($data['payment_methods'])): ?>
    <h2>Payment Methods Breakdown</h2>
    <table>
        <thead>
            <tr>
                <th>Payment Method</th>
                <th>Orders</th>
                <th>Total Amount</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = array_sum(array_column($data['payment_methods'], 'total'));
            foreach ($data['payment_methods'] as $method): 
            ?>
            <tr>
                <td><?php echo ucfirst($method['payment_method']); ?></td>
                <td><?php echo $method['count']; ?></td>
                <td>$<?php echo number_format($method['total'], 2); ?></td>
                <td><?php echo $total > 0 ? round(($method['total'] / $total) * 100, 1) : 0; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <?php if ($include_accounts && !empty($data['accounts'])): ?>
    <h2>Account Balances</h2>
    <table>
        <thead>
            <tr>
                <th>Account Type</th>
                <th>Count</th>
                <th>Current Balance</th>
                <th>Total Credited</th>
                <th>Total Debited</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['accounts'] as $account): ?>
            <tr>
                <td><?php echo ucfirst($account['account_type']); ?></td>
                <td><?php echo $account['count']; ?></td>
                <td>$<?php echo number_format($account['total_balance'], 2); ?></td>
                <td>$<?php echo number_format($account['total_credited'], 2); ?></td>
                <td>$<?php echo number_format($account['total_debited'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (!empty($data['account_details'])): ?>
    <h3>Account Details</h3>
    <table>
        <thead>
            <tr>
                <th>Account Name</th>
                <th>Type</th>
                <th>Email/Number</th>
                <th>Balance</th>
                <th>Default</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['account_details'] as $acc): ?>
            <tr>
                <td><?php echo htmlspecialchars($acc['account_name']); ?></td>
                <td><?php echo ucfirst($acc['account_type']); ?></td>
                <td>
                    <?php if ($acc['account_type'] == 'paypal'): ?>
                        <?php echo $acc['account_email']; ?>
                    <?php else: ?>
                        <?php echo $acc['account_number'] ?? $acc['phone_number']; ?>
                    <?php endif; ?>
                </td>
                <td>$<?php echo number_format($acc['current_balance'], 2); ?></td>
                <td><?php echo $acc['is_default'] ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($report_type == 'withdrawals' && !empty($data['withdrawals'])): ?>
    <h2>Withdrawal Requests</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Vendor</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['withdrawals'] as $w): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($w['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($w['vendor_name']); ?></td>
                <td>$<?php echo number_format($w['request_amount'], 2); ?></td>
                <td><?php echo ucfirst($w['withdrawal_method']); ?></td>
                <td>
                    <span class="status status-<?php echo $w['status']; ?>">
                        <?php echo ucfirst($w['status']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <?php if ($include_transactions && !empty($data['transactions'])): ?>
    <h2>Transactions</h2>
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Date</th>
                <th>Order #</th>
                <th>Customer</th>
                <th>Gateway</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['transactions'] as $txn): ?>
            <tr>
                <td><?php echo $txn['transaction_id']; ?></td>
                <td><?php echo date('d M Y H:i', strtotime($txn['created_at'])); ?></td>
                <td><?php echo $txn['order_number']; ?></td>
                <td><?php echo htmlspecialchars($txn['user_name']); ?></td>
                <td><?php echo ucfirst($txn['gateway']); ?></td>
                <td>$<?php echo number_format($txn['amount'], 2); ?></td>
                <td>
                    <span class="status status-<?php echo $txn['status']; ?>">
                        <?php echo ucfirst($txn['status']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <div class="footer">
        Generated by E-Commerce Payment System | Page 1 of 1
    </div>
</body>
</html>