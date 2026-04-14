<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Student $student;

    public function __construct(Student $student)
    {
        $this->student = $student;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Akun Mobitra Kamu Telah Disetujui!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student_approved',
            with: [
                'studentName' => $this->student->user->name,
                'email'       => $this->student->user->email,
                'sekolah'     => $this->student->sekolah,
                'appName'     => config('app.name', 'Mobitra'),
            ],
        );
    }
}