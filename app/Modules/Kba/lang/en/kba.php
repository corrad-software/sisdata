<?php

return [
    'ai' => [
        'url_tidak_sah' => 'Invalid local AI service URL.',
        'bukan_tempatan' => 'The AI service must be inside the MAIS private network (SRS-KBA-15).',
    ],
    'jawapan' => [
        'tiada_sumber' => 'No sufficient sources were found in the Knowledge Base to answer this question. No answer was generated because it could not be supported by any indexed source. Try a more specific question, use keyword search, or open a support ticket.',
        'ekstraktif_pengenalan' => 'Here are the most relevant passages from the indexed sources (extractive mode — quoted directly from the sources, no AI generation):',
        'had_kadar' => 'AI prompt usage limit reached. Please try again in :saat seconds.',
    ],
    'audit' => [
        'ditolak_had' => 'Prompt refused: AI usage rate limit reached',
    ],
    'indeks' => [
        'tiada_teks' => 'This source has no indexable text.',
        'tanpa_embedding' => 'Local embedding model unavailable — segments indexed for keyword search only. Re-index when the model is available.',
        'dms_tiada' => 'The DMS document was disposed of or no longer exists.',
        'dms_tiada_teks' => 'DMS found no text in this document (e.g. a scan without OCR or a spatial file).',
    ],
    'sumber' => [
        'format_tidak_disokong' => 'Unsupported file format. Use Markdown, text, PDF or DOCX.',
        'gagal_baca' => 'The file could not be read: :ralat',
        'didaftar' => 'Source registered and scheduled for indexing.',
        'dikemas_kini' => 'Source metadata updated; classification and roles applied to its segments immediately.',
        'dinyahaktif' => 'Source deactivated by a content administrator.',
        'indeks_dijadual' => 'Re-indexing scheduled.',
        'indeks_semua_dijadual' => ':bil sources scheduled for (incremental) re-indexing.',
        'dms_tiada' => 'DMS document not found.',
        'tiada_capaian' => 'Source not found or you are not allowed to open it.',
        'urus_melalui_artikel' => 'Article sources are managed through the article itself.',
    ],
    'artikel' => [
        'versi_awal' => 'Initial version',
        'dicipta' => 'Article draft created.',
        'dikemas_kini' => 'Article updated.',
        'dihantar' => 'Article submitted for review.',
        'dilapukkan' => 'Article marked obsolete and removed from search.',
        'lapuk' => 'Article marked obsolete.',
        'sedang_disemak' => 'The article is under review and cannot be edited.',
        'bukan_draf' => 'Only drafts can be submitted for review.',
        'aliran_tiada' => 'Workflow "kba.terbit_artikel" is not configured yet. Contact the system administrator.',
        'tajuk_aliran' => 'Publish article: :tajuk',
        'dari_tiket' => 'Solution draft from ticket :tiket',
        'sebab_keluaran' => 'Refers to system version :lama; release :baharu has been rolled out.',
    ],
    'keluaran' => [
        'direkod' => 'Release recorded. :bil articles flagged for review.',
    ],
    'perbualan' => [
        'disembunyikan' => 'Conversation removed from your history. The audit log is kept.',
    ],
    'maklum_balas' => [
        'terima_kasih' => 'Thank you for your feedback.',
        'dikemas_kini' => 'Feedback action status updated.',
    ],
    'penilaian' => [
        'membantu' => 'Helpful',
        'tidak_membantu' => 'Not helpful',
        'sumber_salah' => 'Wrong source',
    ],
];
