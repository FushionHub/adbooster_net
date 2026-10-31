<?php declare(strict_types=1);

/**
 * Support Ticket System
 * Adsbooster.net
 *
 * Manages customer support tickets including creation, messaging,
 * status updates, and retrieval.
 */

require_once __DIR__ . '/../config/db.php';

class TicketSystem
{
    /** @var array Valid ticket statuses */
    private const VALID_STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];

    /** @var array Valid ticket priorities */
    private const VALID_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** @var array Valid ticket types */
    private const VALID_TYPES = ['general', 'billing', 'technical', 'feature_request', 'bug_report'];

    /**
     * Create a new support ticket.
     *
     * @param int         $userId     The ticket creator's user ID
     * @param string      $type       Ticket type: general|billing|technical|feature_request|bug_report
     * @param string      $priority   Priority: low|medium|high|urgent
     * @param string      $subject    Ticket subject line
     * @param string      $message    Initial message body
     * @param string|null $attachment Optional attachment file path
     * @return int The newly created ticket ID
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If ticket creation fails
     */
    public static function createTicket(
        int $userId,
        string $type,
        string $priority,
        string $subject,
        string $message,
        ?string $attachment = null
    ): int {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid ticket type: {$type}");
        }

        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new \InvalidArgumentException("Invalid priority: {$priority}");
        }

        if (empty(trim($subject))) {
            throw new \InvalidArgumentException('Subject cannot be empty.');
        }

        if (empty(trim($message))) {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }

        $user = db()->fetchOne("SELECT id, full_name, email FROM users WHERE id = ?", [$userId]);
        if ($user === null) {
            throw new \InvalidArgumentException("User not found: {$userId}");
        }

        $ticketNumber = self::generateTicketNumber();

        try {
            $ticketId = db()->insert('tickets', [
                'ticket_number' => $ticketNumber,
                'user_id'       => $userId,
                'type'          => $type,
                'priority'      => $priority,
                'subject'       => trim($subject),
                'status'        => 'open',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            // Create the initial message
            self::addMessageInternal($ticketId, $userId, $message, $attachment, 'customer');

            return $ticketId;

        } catch (\Throwable $e) {
            error_log("Failed to create ticket: " . $e->getMessage());
            throw new \RuntimeException("Failed to create ticket. Please try again.");
        }
    }

    /**
     * Add a message to an existing ticket.
     *
     * @param int         $ticketId   The ticket ID
     * @param int         $senderId   The sender's user ID
     * @param string      $message    Message body
     * @param string|null $attachment Optional attachment file path
     * @return int The newly created message ID
     * @throws \InvalidArgumentException If ticket or sender not found
     * @throws \RuntimeException If message creation fails
     */
    public static function addMessage(
        int $ticketId,
        int $senderId,
        string $message,
        ?string $attachment = null
    ): int {
        $ticket = db()->fetchOne("SELECT id, user_id, status FROM tickets WHERE id = ?", [$ticketId]);
        if ($ticket === null) {
            throw new \InvalidArgumentException("Ticket not found: {$ticketId}");
        }

        $sender = db()->fetchOne(
            "SELECT id, full_name, role FROM users WHERE id = ?",
            [$senderId]
        );
        if ($sender === null) {
            throw new \InvalidArgumentException("Sender not found: {$senderId}");
        }

        // Determine sender role (admin/staff vs customer)
        $senderRole = in_array($sender['role'] ?? '', ['admin', 'staff', 'support'], true) ? 'agent' : 'customer';

        $messageId = self::addMessageInternal($ticketId, $senderId, $message, $attachment, $senderRole);

        // Update ticket status and timestamp
        $newStatus = $ticket['status'] === 'open' && $senderRole === 'agent' ? 'in_progress' : $ticket['status'];
        db()->update('tickets', [
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$ticketId]);

        return $messageId;
    }

    /**
     * Get all tickets for a user, optionally filtered by status.
     *
     * @param int         $userId The user ID
     * @param string|null $status Optional status filter
     * @return array<int, array> List of ticket records
     */
    public static function getTickets(int $userId, ?string $status = null): array
    {
        $sql = "SELECT t.*, 
                       (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count,
                       (SELECT created_at FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_at
                FROM tickets t 
                WHERE t.user_id = ?";
        $params = [$userId];

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY t.updated_at DESC";

        try {
            return db()->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            error_log("Failed to fetch tickets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all messages for a ticket.
     *
     * @param int $ticketId The ticket ID
     * @return array<int, array> List of message records with sender info
     */
    public static function getTicketMessages(int $ticketId): array
    {
        try {
            return db()->fetchAll(
                "SELECT tm.*, u.full_name as sender_name, u.email as sender_email, u.role as sender_role
                 FROM ticket_messages tm
                 JOIN users u ON tm.sender_id = u.id
                 WHERE tm.ticket_id = ?
                 ORDER BY tm.created_at ASC",
                [$ticketId]
            );
        } catch (\Throwable $e) {
            error_log("Failed to fetch ticket messages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update the status of a ticket.
     *
     * @param int    $ticketId The ticket ID
     * @param string $status   New status: open|in_progress|waiting|resolved|closed
     * @return bool True on success
     * @throws \InvalidArgumentException If status is invalid
     */
    public static function updateStatus(int $ticketId, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $ticket = db()->fetchOne("SELECT id, status FROM tickets WHERE id = ?", [$ticketId]);
        if ($ticket === null) {
            return false;
        }

        try {
            $data = [
                'status'     => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($status === 'resolved' || $status === 'closed') {
                $data['resolved_at'] = date('Y-m-d H:i:s');
            }

            db()->update('tickets', $data, 'id = ?', [$ticketId]);
            return true;

        } catch (\Throwable $e) {
            error_log("Failed to update ticket status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a message internally (shared logic between create and add).
     *
     * @param int         $ticketId   Ticket ID
     * @param int         $senderId   Sender user ID
     * @param string      $message    Message content
     * @param string|null $attachment Attachment path
     * @param string      $senderRole Sender role: customer|agent
     * @return int Message ID
     */
    private static function addMessageInternal(
        int $ticketId,
        int $senderId,
        string $message,
        ?string $attachment,
        string $senderRole
    ): int {
        return db()->insert('ticket_messages', [
            'ticket_id'  => $ticketId,
            'sender_id'  => $senderId,
            'sender_role' => $senderRole,
            'message'    => trim($message),
            'attachment' => $attachment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate a unique ticket number.
     *
     * Format: TKT-YYYYMMDD-XXXX
     *
     * @return string
     */
    private static function generateTicketNumber(): string
    {
        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        return "TKT-{$datePart}-{$randomPart}";
    }
}
