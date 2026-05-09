<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Student $student;
    public string $reason;

    public function __construct(Student $student, string $reason)
    {
        $this->student = $student;
        $this->reason  = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Pendaftaran Akun Mobitra Kamu Ditolak',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student_rejected',
            with: [
                'studentName' => $this->student->user->name,
                'email'       => $this->student->user->email,
                'reason'      => $this->reason,
                'appName'     => config('app.name', 'Mobitra'),
            ],
        );
    }
}