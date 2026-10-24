<?php

return [
    'saved' => 'Changes saved.',

    'attributes' => [],

    'parameter' => [
        'unknown' => 'Unknown parameter.',
        'value' => 'value',
        'security_scope' => 'Critical parameters (security, session, retention) can only be changed by a System Administrator.',
    ],

    'kelulusan' => [
        'already_pending' => 'A change to this configuration is already awaiting approval.',
        'not_pending' => 'This request has already been decided.',
        'only_requester_cancels' => 'Only the requester can cancel this request.',
        'second_admin_required' => 'A critical configuration change must be approved by another administrator, not the requester.',
        'decide_in_workflow' => 'This request is handled by the workflow engine; decide it from the workflow task list.',
        'workflow_title' => 'Critical configuration change: :kunci',
        'submitted' => 'Critical configuration change submitted for second-admin approval. It is not in effect yet.',
        'approved' => 'Change approved and now in effect.',
        'rejected' => 'Change rejected.',
        'cancelled' => 'Request cancelled.',
    ],

    'kod' => [
        'attributes' => [],
        'parent_missing' => 'Parent code does not exist in type :jenis.',
        'inactive' => 'inactive',
        'deactivated' => 'Code deactivated. Historical records using it remain valid.',
        'imported' => 'Import finished: :diterima accepted, :ditolak rejected.',
        'csv_unreadable' => 'The CSV file could not be read.',
        'types' => [],
    ],

    'templat' => [
        'unknown_fields' => 'Content uses undeclared fields: :medan',
        'restored' => 'Version :versi restored as a new version.',
    ],

    'tugasan' => [
        'already_running' => 'Skipped: the same job is already running.',
        'command_not_allowed' => 'Command ":arahan" may not be scheduled.',
        'exit_code' => 'Command exited with code :kod.',
        'deactivated_has_history' => 'The job has run history, so it was deactivated instead of deleted.',
        'queued' => 'Job queued for execution.',
        'system_not_found' => 'System job not found in the scheduler.',
        'invalid_cron' => 'Invalid cron expression.',
        'stuck' => 'Run exceeded its timeout and was marked failed.',
    ],

    'kesihatan' => [
        'queue_over' => 'Queue size :saiz exceeds threshold :ambang.',
        'horizon_down' => 'No Horizon supervisor is running.',
        'storage_over' => 'Storage usage :peratus% exceeds threshold :ambang%.',
    ],

    'integrasi' => [
        'no_change' => 'Nothing changed.',
    ],

    'penyelenggaraan' => [
        'default_notice' => 'The system is under maintenance. Please try again shortly.',
    ],

    'carian' => [
        'reindex_queued' => 'Reindex queued.',
    ],

    'token' => [
        'scope' => 'This token does not carry the admin API scope.',
        'session_only' => 'API tokens can only be created from a signed-in session, not with another token.',
        'created' => 'Token created. Copy it now — it will not be shown again.',
    ],
];
