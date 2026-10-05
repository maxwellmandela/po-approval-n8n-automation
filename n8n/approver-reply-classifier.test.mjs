import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const codeNode = await readFile(new URL('./approver-reply-classifier.js', import.meta.url), 'utf8');

function classifyReply(reply) {
    const runCodeNode = new Function('$input', codeNode);

    return runCodeNode({ all: () => [{ json: reply }] })[0].json;
}

const baseReply = {
    id: 'gmail-123',
    from: 'Finance Approver <finance@example.com>',
    subject: 'Re: [PR-2026-0042] Approval needed',
    payload: {
        headers: [{ name: 'Authentication-Results', value: 'mx.google.com; dmarc=pass header.from=example.com' }],
    },
};

test('recognizes natural-language clarification requests', () => {
    const result = classifyReply({ ...baseReply, textPlain: 'Please clarify why this model is required.' });

    assert.equal(result.action, 'clarify');
    assert.equal(result.request_number, 'PR-2026-0042');
});

test('recognizes explicit approvals and rejections', () => {
    assert.equal(classifyReply({ ...baseReply, textPlain: 'Approved. Please proceed.' }).action, 'approve');
    assert.equal(classifyReply({ ...baseReply, textPlain: 'Reject. The quote is over budget.' }).action, 'reject');
});

test('routes plain-text replies on clarification email threads as requester responses', () => {
    const result = classifyReply({
        ...baseReply,
        subject: 'Re: [PR-2026-0042] Clarification requested',
        textPlain: 'The laptop meets our approved development image requirements.',
    });

    assert.equal(result.action, 'clarification_response');
    assert.equal(result.sender_authenticated, true);
});

test('routes conflicting or unsupported cancellation replies to review', () => {
    assert.equal(classifyReply({ ...baseReply, textPlain: 'Approved, but please clarify the delivery date.' }).action, 'needs_review');
    assert.equal(classifyReply({ ...baseReply, textPlain: 'Cancel this request.' }).action, 'needs_review');
});

test('routes replies without a request number to review', () => {
    const result = classifyReply({ ...baseReply, subject: 'Re: Approval needed', textPlain: 'Approved.' });

    assert.equal(result.action, 'needs_review');
});

test('routes messages without DMARC authentication to review', () => {
    const result = classifyReply({ ...baseReply, payload: { headers: [] }, textPlain: 'Approved.' });

    assert.equal(result.action, 'needs_review');
    assert.match(result.classification_reason, /DMARC/);
});

test('extracts sender and plain text from Gmail API payloads', () => {
    const result = classifyReply({
        id: 'gmail-124',
        payload: {
            headers: [
                { name: 'From', value: 'Finance Approver <finance@example.com>' },
                { name: 'Subject', value: 'Re: [PR-2026-0042] Approval needed' },
                { name: 'Authentication-Results', value: 'mx.google.com; dmarc=pass header.from=example.com' },
            ],
            mimeType: 'text/plain',
            body: { data: Buffer.from('Please clarify why this model is required.').toString('base64url') },
        },
    });

    assert.equal(result.action, 'clarify');
    assert.equal(result.sender_email, 'finance@example.com');
});