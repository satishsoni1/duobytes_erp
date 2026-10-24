<?php

return [

    /*
    | Product branding (DuoBytes ERP). The customer's own company name and logo come from Settings
    | and are what appear on quotations, invoices and other customer-facing documents.
    */
    'brand' => [
        'product' => 'DuoBytes ERP',
        'vendor' => 'DuoBytes Technologies Pvt Ltd',
        'logo' => 'images/brand/duobytes-erp-logo.png',
        'mark' => 'images/brand/duobytes-mark.png',
        'chart_palette' => ['#0D4E98', '#F27A1A', '#1675CF', '#FDA417', '#083A7C', '#D65518', '#4FA3E8', '#2E9E6B', '#7A8CA8', '#B04A8F', '#C9A227', '#5B6B7B'],
    ],

    'currency' => env('CRM_CURRENCY', '₹'),

    'default_country_code' => env('CRM_COUNTRY_CODE', '91'),

    'roles' => [
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'sales' => 'Sales Executive',
        'inventory' => 'Inventory / Store',
        'technician' => 'Technician',
        'support' => 'Customer Support',
    ],

    /*
    | Ability => roles allowed. Admins pass every check (see AuthServiceProvider logic in AppServiceProvider).
    */
    'permissions' => [
        'dashboard' => ['manager', 'sales', 'inventory', 'technician', 'support'],
        'leads' => ['manager', 'sales', 'support'],
        'customers' => ['manager', 'sales', 'support'],
        'sales' => ['manager', 'sales'],
        'payments' => ['manager', 'sales'],
        'inventory' => ['manager', 'inventory'],
        'inventory.view' => ['manager', 'inventory', 'sales'],
        'purchases' => ['manager', 'inventory'],
        'installations' => ['manager', 'sales', 'technician', 'support'],
        'service' => ['manager', 'support', 'technician', 'sales'],
        'whatsapp' => ['manager', 'sales', 'support'],
        'reports' => ['manager'],
        'tally' => ['manager'],
        'users' => [],
        'settings' => [],
    ],

    'lead_sources' => [
        'website' => 'Website Form',
        'facebook' => 'Facebook Lead Ads',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'google_ads' => 'Google Ads',
        'indiamart' => 'IndiaMART / Marketplace',
        'walk_in' => 'Showroom Walk-in',
        'phone' => 'Phone Call',
        'referral' => 'Referral',
        'architect' => 'Architect / Interior Designer',
        'exhibition' => 'Exhibition / Event',
        'other' => 'Other',
    ],

    'lead_statuses' => [
        'new' => ['label' => 'New', 'color' => 'secondary'],
        'contacted' => ['label' => 'Contacted', 'color' => 'info'],
        'qualified' => ['label' => 'Qualified', 'color' => 'primary'],
        'showroom_visit' => ['label' => 'Showroom Visit', 'color' => 'purple'],
        'quotation_sent' => ['label' => 'Quotation Sent', 'color' => 'warning'],
        'negotiation' => ['label' => 'Negotiation', 'color' => 'orange'],
        'won' => ['label' => 'Won', 'color' => 'success'],
        'lost' => ['label' => 'Lost', 'color' => 'danger'],
    ],

    'lead_priorities' => ['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'],

    'lost_reasons' => [
        'price' => 'Price too high',
        'competitor' => 'Bought from competitor',
        'no_response' => 'No response',
        'not_interested' => 'Not interested',
        'out_of_stock' => 'Product not available',
        'delivery_time' => 'Delivery time too long',
        'budget' => 'Budget postponed',
        'other' => 'Other',
    ],

    'customer_types' => [
        'individual' => 'Individual',
        'business' => 'Business / Corporate',
        'architect' => 'Architect / Interior Designer',
        'dealer' => 'Dealer / Reseller',
        'builder' => 'Builder / Contractor',
    ],

    'follow_up_types' => [
        'call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email',
        'meeting' => 'Meeting', 'showroom_visit' => 'Showroom Visit', 'site_visit' => 'Site Visit',
    ],

    'activity_types' => [
        'note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp',
        'meeting' => 'Meeting', 'status' => 'Status Change', 'system' => 'System',
    ],

    'quotation_statuses' => [
        'draft' => 'secondary', 'sent' => 'info', 'accepted' => 'success',
        'rejected' => 'danger', 'expired' => 'dark', 'converted' => 'primary',
    ],

    'order_statuses' => [
        'pending' => ['label' => 'Pending', 'color' => 'secondary'],
        'confirmed' => ['label' => 'Confirmed', 'color' => 'info'],
        'in_production' => ['label' => 'In Production', 'color' => 'purple'],
        'ready' => ['label' => 'Ready to Dispatch', 'color' => 'primary'],
        'dispatched' => ['label' => 'Dispatched', 'color' => 'warning'],
        'delivered' => ['label' => 'Delivered', 'color' => 'success'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'danger'],
    ],

    // Stock is deducted from the order's warehouse once the order reaches one of these statuses.
    'stock_deduct_statuses' => ['confirmed', 'in_production', 'ready', 'dispatched', 'delivered', 'completed'],

    'payment_methods' => [
        'cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque', 'finance' => 'EMI / Finance',
    ],

    'purchase_statuses' => [
        'draft' => 'secondary', 'ordered' => 'info', 'partial' => 'warning',
        'received' => 'success', 'cancelled' => 'danger',
    ],

    'stock_movement_types' => [
        'opening' => 'Opening Stock', 'purchase' => 'Purchase Receipt', 'sale' => 'Sale',
        'sale_return' => 'Sale Return / Restock', 'adjustment' => 'Adjustment',
        'transfer_in' => 'Transfer In', 'transfer_out' => 'Transfer Out', 'damage' => 'Damaged / Scrap',
    ],

    'installation_statuses' => [
        'pending' => ['label' => 'Pending', 'color' => 'secondary'],
        'scheduled' => ['label' => 'Scheduled', 'color' => 'info'],
        'in_progress' => ['label' => 'In Progress', 'color' => 'warning'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'rescheduled' => ['label' => 'Rescheduled', 'color' => 'purple'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'danger'],
    ],

    'time_slots' => [
        '09-12' => '09:00 AM - 12:00 PM',
        '12-15' => '12:00 PM - 03:00 PM',
        '15-18' => '03:00 PM - 06:00 PM',
        '18-21' => '06:00 PM - 09:00 PM',
    ],

    'ticket_types' => [
        'repair' => 'Repair', 'replacement' => 'Replacement', 'warranty' => 'Warranty Claim',
        'complaint' => 'Complaint', 'assembly' => 'Assembly / Re-fitting', 'polish' => 'Polishing / Refinish',
        'upholstery' => 'Upholstery', 'pest' => 'Termite / Pest Treatment', 'enquiry' => 'General Enquiry',
    ],

    'ticket_statuses' => [
        'open' => ['label' => 'Open', 'color' => 'secondary'],
        'assigned' => ['label' => 'Assigned', 'color' => 'info'],
        'in_progress' => ['label' => 'In Progress', 'color' => 'warning'],
        'awaiting_parts' => ['label' => 'Awaiting Parts', 'color' => 'purple'],
        'resolved' => ['label' => 'Resolved', 'color' => 'success'],
        'closed' => ['label' => 'Closed', 'color' => 'dark'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'danger'],
    ],

    'priorities' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'],

    // SLA hours by ticket priority
    'sla_hours' => ['low' => 120, 'medium' => 72, 'high' => 48, 'urgent' => 24],

    'ticket_sources' => [
        'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email',
        'walk_in' => 'Walk-in', 'website' => 'Website', 'technician' => 'Technician Visit',
    ],

    'units' => ['pcs' => 'Pieces', 'set' => 'Set', 'sqft' => 'Sq. Ft', 'rft' => 'Running Ft', 'mtr' => 'Meter', 'kg' => 'Kg'],

    /*
    | Automated customer WhatsApp notifications. Each can be mapped (in Settings) to an approved
    | Meta template whose body variables {{1}}, {{2}}… receive the listed params in order.
    | Without a template the default text is sent, which Meta only delivers inside the 24h window.
    */
    'notification_events' => [
        'lead_welcome' => [
            'label' => 'New lead welcome',
            'params' => ['name', 'company'],
            'text' => "Hi {name}, thank you for your interest in {company}! Our furniture consultant will contact you shortly.",
        ],
        'quotation_sent' => [
            'label' => 'Quotation shared',
            'params' => ['name', 'number', 'total', 'valid_until', 'link'],
            'text' => "Hi {name}, please find your quotation {number} for {total}, valid until {valid_until}: {link}",
        ],
        'order_confirmed' => [
            'label' => 'Order confirmed',
            'params' => ['name', 'number', 'total', 'delivery_date'],
            'text' => "Hi {name}, your order {number} worth {total} is confirmed. Expected delivery: {delivery_date}.",
        ],
        'order_dispatched' => [
            'label' => 'Order dispatched',
            'params' => ['name', 'number'],
            'text' => "Hi {name}, your order {number} has been dispatched and will reach you soon.",
        ],
        'order_delivered' => [
            'label' => 'Order delivered',
            'params' => ['name', 'number'],
            'text' => "Hi {name}, your order {number} has been delivered. Thank you for shopping with us!",
        ],
        'payment_received' => [
            'label' => 'Payment received',
            'params' => ['name', 'amount', 'number', 'balance'],
            'text' => "Hi {name}, we received {amount} against order {number}. Balance due: {balance}.",
        ],
        'installation_scheduled' => [
            'label' => 'Installation scheduled',
            'params' => ['name', 'number', 'date', 'slot', 'technician'],
            'text' => "Hi {name}, installation {number} is scheduled on {date} ({slot}). Technician: {technician}.",
        ],
        'installation_completed' => [
            'label' => 'Installation completed',
            'params' => ['name', 'number'],
            'text' => "Hi {name}, installation {number} is complete. Please rate our service from 1 to 5 by replying here.",
        ],
        'ticket_created' => [
            'label' => 'Service ticket created',
            'params' => ['name', 'number', 'subject'],
            'text' => "Hi {name}, your service request {number} ({subject}) is registered. We will get back to you shortly.",
        ],
        'ticket_visit_scheduled' => [
            'label' => 'Service visit scheduled',
            'params' => ['name', 'number', 'date', 'slot'],
            'text' => "Hi {name}, a technician visit for request {number} is scheduled on {date} ({slot}).",
        ],
        'ticket_resolved' => [
            'label' => 'Service ticket resolved',
            'params' => ['name', 'number'],
            'text' => "Hi {name}, your service request {number} has been resolved. Thank you for your patience.",
        ],
        'birthday_wish' => [
            'label' => 'Birthday wishes',
            'params' => ['name', 'company'],
            'text' => "Happy Birthday {name}! Warm wishes from all of us at {company}.",
        ],
    ],

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
    ],

    'meta' => [
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'verify_token' => env('META_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
    ],
];
