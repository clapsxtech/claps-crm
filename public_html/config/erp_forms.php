<?php
return json_decode(<<<'JSON'
{
  "people": [
    {
      "title": "Register a student",
      "action": "student",
      "workspace": true,
      "fields": [
        {
          "name": "code",
          "label": "Code",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "name",
          "label": "Name",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "registered_on",
          "label": "Registered On",
          "type": "date",
          "required": true,
          "options": null
        },
        {
          "name": "timezone",
          "label": "Timezone",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "grade",
          "label": "Grade",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "syllabus",
          "label": "Syllabus",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "guardian_contact",
          "label": "Guardian Contact",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "meeting_url",
          "label": "Meeting Url",
          "type": "url",
          "required": false,
          "options": null
        }
      ],
      "help": ""
    },
    {
      "title": "Register a teacher",
      "action": "teacher",
      "workspace": true,
      "fields": [
        {
          "name": "code",
          "label": "Code",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "name",
          "label": "Name",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "timezone",
          "label": "Timezone",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": ""
    },
    {
      "title": "Update student details",
      "action": "student-update",
      "workspace": false,
      "fields": [
        {
          "name": "student_id",
          "label": "Student",
          "type": "lookup",
          "required": true,
          "options": [
            "students"
          ]
        },
        {
          "name": "name",
          "label": "Name",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "registered_on",
          "label": "Registered On",
          "type": "date",
          "required": false,
          "options": null
        },
        {
          "name": "status",
          "label": "Status",
          "type": "select",
          "required": false,
          "options": [
            "active",
            "paused",
            "stopped"
          ]
        },
        {
          "name": "grade",
          "label": "Grade",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "syllabus",
          "label": "Syllabus",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "timezone",
          "label": "Timezone",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "guardian_contact",
          "label": "Guardian Contact",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "meeting_url",
          "label": "Meeting Url",
          "type": "url",
          "required": false,
          "options": null
        }
      ],
      "help": "Blank fields are left unchanged. Pausing/stopping requires resolving future classes first."
    }
  ],
  "money": [
    {
      "title": "Record received student payment",
      "action": "payment",
      "workspace": true,
      "fields": [
        {
          "name": "student_id",
          "label": "Student",
          "type": "lookup",
          "required": true,
          "options": [
            "students"
          ]
        },
        {
          "name": "amount_inr",
          "label": "Amount (INR)",
          "type": "money",
          "required": true,
          "options": null
        },
        {
          "name": "reference",
          "label": "Reference",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": "Bookkeeping only. Enter a payment already received; this does not collect money."
    },
    {
      "title": "Create payment request",
      "action": "request-create",
      "workspace": false,
      "fields": [
        {
          "name": "student_id",
          "label": "Student",
          "type": "lookup",
          "required": true,
          "options": [
            "students"
          ]
        },
        {
          "name": "amount_inr",
          "label": "Amount (INR)",
          "type": "money",
          "required": true,
          "options": null
        },
        {
          "name": "reference",
          "label": "Reference",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "requested_on",
          "label": "Requested On",
          "type": "date",
          "required": true,
          "options": null
        },
        {
          "name": "due_on",
          "label": "Due On",
          "type": "date",
          "required": false,
          "options": null
        },
        {
          "name": "notes",
          "label": "Notes",
          "type": "text",
          "required": false,
          "options": null
        }
      ],
      "help": "A request does not change balances and is not a receipt or tax invoice."
    },
    {
      "title": "Link received payment to request",
      "action": "request-allocate",
      "workspace": false,
      "fields": [
        {
          "name": "request_id",
          "label": "Payment request",
          "type": "lookup",
          "required": true,
          "options": [
            "requests"
          ]
        },
        {
          "name": "payment_id",
          "label": "Receipt",
          "type": "lookup",
          "required": true,
          "options": [
            "payments"
          ]
        },
        {
          "name": "amount_inr",
          "label": "Amount (INR)",
          "type": "money",
          "required": true,
          "options": null
        }
      ],
      "help": "Allocate only money actually received. Reversed receipts stop covering requests automatically."
    },
    {
      "title": "Cancel remaining payment request",
      "action": "request-cancel",
      "workspace": false,
      "fields": [
        {
          "name": "request_id",
          "label": "Payment request",
          "type": "lookup",
          "required": true,
          "options": [
            "requests"
          ]
        },
        {
          "name": "reason",
          "label": "Reason",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": ""
    },
    {
      "title": "Correct recorded receipt amount",
      "action": "receipt-correct",
      "workspace": false,
      "fields": [
        {
          "name": "entry_id",
          "label": "Ledger entry",
          "type": "lookup",
          "required": true,
          "options": [
            "payments"
          ]
        },
        {
          "name": "amount_inr",
          "label": "Amount (INR)",
          "type": "money",
          "required": true,
          "options": null
        },
        {
          "name": "new_reference",
          "label": "New Reference",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "reason",
          "label": "Reason",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": "Reverses the old bookkeeping entry and records a replacement atomically. Does not refund or collect money."
    },
    {
      "title": "Reverse mistaken receipt record",
      "action": "receipt-reverse",
      "workspace": false,
      "fields": [
        {
          "name": "entry_id",
          "label": "Ledger entry",
          "type": "lookup",
          "required": true,
          "options": [
            "payments"
          ]
        },
        {
          "name": "reason",
          "label": "Reason",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": "Bookkeeping reversal only. No bank refund or transfer is performed."
    },
    {
      "title": "Record already-paid teacher settlement",
      "action": "salary-paid",
      "workspace": false,
      "fields": [
        {
          "name": "teacher_id",
          "label": "Teacher",
          "type": "lookup",
          "required": true,
          "options": [
            "teachers"
          ]
        },
        {
          "name": "amount_inr",
          "label": "Amount (INR)",
          "type": "money",
          "required": true,
          "options": null
        },
        {
          "name": "reference",
          "label": "Reference",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "paid_on",
          "label": "Paid On",
          "type": "date",
          "required": true,
          "options": null
        },
        {
          "name": "reason",
          "label": "Reason",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "already_paid",
          "label": "Already Paid",
          "type": "select",
          "required": true,
          "options": [
            "1"
          ]
        }
      ],
      "help": "Confirm this amount was already paid externally. Advances/overpayments need separate review. No money is sent."
    },
    {
      "title": "Reverse mistaken teacher settlement record",
      "action": "salary-reverse",
      "workspace": false,
      "fields": [
        {
          "name": "entry_id",
          "label": "Ledger entry",
          "type": "lookup",
          "required": true,
          "options": [
            "salary"
          ]
        },
        {
          "name": "reason",
          "label": "Reason",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": "Corrects bookkeeping only; it does not recover or transfer funds."
    }
  ],
  "followups": [
    {
      "title": "Create assigned follow-up task",
      "action": "task",
      "workspace": true,
      "fields": [
        {
          "name": "title",
          "label": "Title",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "student_id",
          "label": "Student",
          "type": "lookup",
          "required": false,
          "options": [
            "students"
          ]
        },
        {
          "name": "assigned_to",
          "label": "Assigned To",
          "type": "lookup",
          "required": false,
          "options": [
            "members"
          ]
        },
        {
          "name": "due_on",
          "label": "Due On",
          "type": "date",
          "required": false,
          "options": null
        },
        {
          "name": "notes",
          "label": "Notes",
          "type": "text",
          "required": false,
          "options": null
        }
      ],
      "help": ""
    },
    {
      "title": "Update task",
      "action": "task-update",
      "workspace": false,
      "fields": [
        {
          "name": "task_id",
          "label": "Task",
          "type": "lookup",
          "required": true,
          "options": [
            "tasks"
          ]
        },
        {
          "name": "title",
          "label": "Title",
          "type": "text",
          "required": false,
          "options": null
        },
        {
          "name": "assigned_to",
          "label": "Assigned To",
          "type": "lookup",
          "required": false,
          "options": [
            "members"
          ]
        },
        {
          "name": "due_on",
          "label": "Due On",
          "type": "date",
          "required": false,
          "options": null
        },
        {
          "name": "status",
          "label": "Status",
          "type": "select",
          "required": true,
          "options": [
            "open",
            "in_progress",
            "completed"
          ]
        },
        {
          "name": "notes",
          "label": "Notes",
          "type": "text",
          "required": false,
          "options": null
        }
      ],
      "help": "Blank optional fields remain unchanged."
    },
    {
      "title": "Add checklist item",
      "action": "task-item",
      "workspace": false,
      "fields": [
        {
          "name": "task_id",
          "label": "Task",
          "type": "lookup",
          "required": true,
          "options": [
            "tasks"
          ]
        },
        {
          "name": "title",
          "label": "Title",
          "type": "text",
          "required": true,
          "options": null
        }
      ],
      "help": ""
    },
    {
      "title": "Complete checklist item",
      "action": "task-item-complete",
      "workspace": false,
      "fields": [
        {
          "name": "item_id",
          "label": "Checklist item",
          "type": "lookup",
          "required": true,
          "options": [
            "items"
          ]
        }
      ],
      "help": ""
    },
    {
      "title": "Record completed WhatsApp/call follow-up",
      "action": "followup",
      "workspace": false,
      "fields": [
        {
          "name": "student_id",
          "label": "Student",
          "type": "lookup",
          "required": true,
          "options": [
            "students"
          ]
        },
        {
          "name": "contact_channel",
          "label": "Contact Channel",
          "type": "select",
          "required": true,
          "options": [
            "whatsapp",
            "call"
          ]
        },
        {
          "name": "note",
          "label": "Note",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "outcome",
          "label": "Outcome",
          "type": "text",
          "required": true,
          "options": null
        },
        {
          "name": "next_due_on",
          "label": "Next Due On",
          "type": "date",
          "required": false,
          "options": null
        }
      ],
      "help": "Records a conversation that already happened. Does not contact anyone."
    }
  ],
  "staff": [
    {
      "title": "Grant or update unit membership",
      "action": "membership-grant",
      "workspace": false,
      "fields": [
        {
          "name": "user_id",
          "label": "Existing account",
          "type": "lookup",
          "required": true,
          "options": [
            "users"
          ]
        },
        {
          "name": "role",
          "label": "Role",
          "type": "select",
          "required": true,
          "options": [
            "admin",
            "sales",
            "teacher"
          ]
        }
      ],
      "help": "Existing active site accounts only. Administrator grants are limited to this unit; no external account access is created."
    },
    {
      "title": "Remove unit membership",
      "action": "membership-revoke",
      "workspace": false,
      "fields": [
        {
          "name": "user_id",
          "label": "Existing account",
          "type": "lookup",
          "required": true,
          "options": [
            "revocable_members"
          ]
        }
      ],
      "help": "Last-admin and active teacher-link safeguards apply."
    },
    {
      "title": "Explicitly link teacher to login account",
      "action": "teacher-link",
      "workspace": false,
      "fields": [
        {
          "name": "teacher_id",
          "label": "Teacher",
          "type": "lookup",
          "required": true,
          "options": [
            "teachers"
          ]
        },
        {
          "name": "user_id",
          "label": "Existing account",
          "type": "lookup",
          "required": true,
          "options": [
            "members"
          ]
        }
      ],
      "help": "Grant teacher membership first. Identity is never inferred from names."
    },
    {
      "title": "Unlink teacher login account",
      "action": "teacher-unlink",
      "workspace": false,
      "help": "Removes linked login access immediately with a reason; preserves teacher history and scheduled lessons. Membership may then be revoked.",
      "fields": [
        {
          "name": "teacher_id",
          "label": "Teacher",
          "type": "lookup",
          "required": true,
          "options": [
            "teachers"
          ]
        },
        {
          "name": "reason",
          "label": "Reason",
          "type": "text",
          "required": true,
          "options": null
        }
      ]
    }
  ]
}
JSON, true);
