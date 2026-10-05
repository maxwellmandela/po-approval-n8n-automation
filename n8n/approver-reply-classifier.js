return $input.all().map((item) => {
    const email = item.json;
    const headers = Array.isArray(email.payload?.headers)
        ? email.payload.headers
        : Array.isArray(email.headers)
            ? email.headers
            : [];
    const getHeader = (name) => headers.find((header) => String(header.name ?? '').toLowerCase() === name)?.value ?? '';
    const extractPlainText = (part) => {
        if (part.mimeType === 'text/plain' && part.body?.data) {
            const data = part.body.data.replace(/-/g, '+').replace(/_/g, '/');
            return Buffer.from(data, 'base64').toString('utf8');
        }

        return (part.parts ?? []).map(extractPlainText).filter(Boolean).join('\n');
    };
    const subject = String(email.subject ?? email.Subject ?? getHeader('subject'));
    const rawBody = String(email.textPlain ?? email.text ?? email.body ?? extractPlainText(email.payload ?? {}) ?? email.snippet ?? '');
    const currentBody = rawBody
        .replace(/\r/g, '')
        .split(/\n(?:On .{1,200}wrote:|From: .+|>.*)/i)[0]
        .trim();
    const fromValue = email.from ?? email.From ?? email.sender ?? getHeader('from');
    const fromText = typeof fromValue === 'string'
        ? fromValue
        : String(fromValue.email ?? fromValue.address ?? '');
    const senderEmail = fromText.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i)?.[0]?.toLowerCase() ?? '';
    const messageId = String(email.id ?? email.messageId ?? email.message_id ?? '');
    const requestNumber = `${subject}\n${currentBody}`.match(/\bPR-\d{4}-\d{4,}\b/i)?.[0]?.toUpperCase() ?? '';
    const authenticationResults = String(email.authenticationResults ?? getHeader('authentication-results'));
    const senderAuthenticated = /\bdmarc=pass\b/i.test(authenticationResults);
    const cancellation = /\b(?:cancel(?:lation)?|withdraw(?:al)?)\b/i.test(currentBody);
    const asksClarification = /\b(?:clarif(?:y|ication)|please explain|could you explain|need more (?:information|details)|please (?:send|provide) (?:more|additional) (?:information|details))\b/i.test(currentBody);
    const rejects = /\b(?:reject(?:ed)?|decline(?:d)?|do not approve|don't approve|cannot approve|can't approve|not approved)\b/i.test(currentBody);
    const approves = /^\s*(?:(?:yes|okay|ok)[,.!\s]+)?(?:i\s+)?(?:approve(?:d)?|looks good(?: to me)?|go ahead|proceed)\b/i.test(currentBody);
    let action = 'needs_review';
    let classificationReason = 'No single explicit decision was recognized.';

    if (cancellation) {
        classificationReason = 'Cancellation is not an approver action supported by the workflow.';
    } else if (/\bclarification requested\b/i.test(subject)) {
        action = 'clarification_response';
        classificationReason = 'Reply belongs to a clarification email thread.';
    } else {
        const detectedActions = [asksClarification, rejects, approves].filter(Boolean).length;

        if (detectedActions === 1 && asksClarification) {
            action = 'clarify';
            classificationReason = 'Clarification request recognized.';
        } else if (detectedActions === 1 && rejects) {
            action = 'reject';
            classificationReason = 'Rejection recognized.';
        } else if (detectedActions === 1 && approves) {
            action = 'approve';
            classificationReason = 'Approval recognized.';
        } else if (detectedActions > 1) {
            classificationReason = 'Conflicting decisions were found in the reply.';
        }
    }

    if (!requestNumber || !senderEmail || !messageId) {
        action = 'needs_review';
        classificationReason = 'Request number, sender email, or provider message ID is missing.';
    } else if (!senderAuthenticated) {
        action = 'needs_review';
        classificationReason = 'The message does not have a passing DMARC result.';
    }

    return {
        json: {
            ...email,
            message_id: messageId,
            request_number: requestNumber,
            sender_email: senderEmail,
            sender_authenticated: senderAuthenticated,
            action,
            comment: currentBody,
            classification_reason: classificationReason,
        },
    };
});