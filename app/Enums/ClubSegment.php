<?php

namespace App\Enums;

/**
 * Types of business with their own landing page. The value is the Dutch URL
 * slug, so the pages rank on the terms owners search for (/voor/dansschool).
 */
enum ClubSegment: string
{
    case DanceSchool = 'dansschool';
    case PersonalTrainer = 'personal-trainer';
    case BoutiqueFitness = 'boutique-fitness';
    case FitnessClub = 'sportschool';
    case BoxingSchool = 'boksschool';

    public function label(): string
    {
        return match ($this) {
            self::DanceSchool => __('Dance schools'),
            self::PersonalTrainer => __('Personal trainers'),
            self::BoutiqueFitness => __('Boutique fitness studios'),
            self::FitnessClub => __('Gyms'),
            self::BoxingSchool => __('Boxing schools'),
        };
    }

    /**
     * One line for the overview cards that link to the segment pages.
     */
    public function cardText(): string
    {
        return match ($this) {
            self::DanceSchool => __('Class groups per season, trial classes and fees.'),
            self::PersonalTrainer => __('Sessions, packages and intake forms.'),
            self::BoutiqueFitness => __('Class booking, credits and full classes.'),
            self::FitnessClub => __('Member management, check-in and automatic direct debit.'),
            self::BoxingSchool => __('Sessions per level and digital declarations.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DanceSchool => 'sparkles',
            self::PersonalTrainer => 'user',
            self::BoutiqueFitness => 'bolt',
            self::FitnessClub => 'building-storefront',
            self::BoxingSchool => 'rocket-launch',
        };
    }

    public function metaTitle(): string
    {
        return match ($this) {
            self::DanceSchool => __('Software for dance schools: schedule, members and payments'),
            self::PersonalTrainer => __('Software for personal trainers: sessions, strip cards and payments'),
            self::BoutiqueFitness => __('Software for boutique fitness: class booking, credits and memberships'),
            self::FitnessClub => __('Software for gyms: member management, check-in and direct debit'),
            self::BoxingSchool => __('Software for boxing and martial arts schools: training, members and fees'),
        };
    }

    public function metaDescription(): string
    {
        $days = ['days' => ClubPlan::TRIAL_DAYS];

        return match ($this) {
            self::DanceSchool => __('Run your dance school with SquadPass: fixed class groups, drop-in classes, strip cards and automatic direct debit. :days days free, cancel monthly.', $days),
            self::PersonalTrainer => __('Plan your PT sessions, sell strip cards and receive payments automatically with SquadPass. Professional and affordable. Try it free for :days days.', $days),
            self::BoutiqueFitness => __('Let members book classes themselves, sell credits and memberships and fill every class with SquadPass. Made for yoga, pilates, spinning and HIIT studios. :days days free.', $days),
            self::FitnessClub => __('Manage members, memberships, group classes and automatic direct debit for your gym in one platform. Switch easily with CSV import. :days days free.', $days),
            self::BoxingSchool => __('Plan boxing sessions per level, let members enroll themselves and collect fees automatically. Also for kickboxing, MMA and other martial arts. :days days free.', $days),
        };
    }

    public function eyebrow(): string
    {
        return match ($this) {
            self::DanceSchool => __('For dance schools'),
            self::PersonalTrainer => __('For personal trainers'),
            self::BoutiqueFitness => __('For boutique fitness studios'),
            self::FitnessClub => __('For gyms'),
            self::BoxingSchool => __('For boxing and martial arts schools'),
        };
    }

    /**
     * The hero headline in two parts: the second part is highlighted.
     *
     * @return array{string, string}
     */
    public function headline(): array
    {
        return match ($this) {
            self::DanceSchool => [__('More time on the dance floor,'), __('less time on admin.')],
            self::PersonalTrainer => [__('You train your clients,'), __('SquadPass does the rest.')],
            self::BoutiqueFitness => [__('Full classes,'), __('without the hassle.')],
            self::FitnessClub => [__('Your gym,'), __('fully under control.')],
            self::BoxingSchool => [__('Focus on the ring,'), __('not on the paperwork.')],
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::DanceSchool => __('From toddler ballet to advanced salsa: SquadPass handles your class groups, enrollments, absences and fees, so you can focus on teaching.'),
            self::PersonalTrainer => __('Scheduling, strip cards, intake forms and payments in one app. You look professional and keep more hours for what you love most: training.'),
            self::BoutiqueFitness => __('For yoga, pilates, spinning, HIIT and bootcamp: members book their own spot, pay with credits or a membership and check in at the door. You keep the overview and fill every class.'),
            self::FitnessClub => __('Member administration, memberships, group classes and direct debit in one clear platform. Switch easily with your existing member list and keep a grip on your revenue.'),
            self::BoxingSchool => __('Whether you teach boxing, kickboxing or MMA: SquadPass plans your sessions per level, tracks who trains and collects fees automatically.'),
        };
    }

    /**
     * Example club and schedule for the hero mockup.
     *
     * @return array{club: string, lessons: list<array{time: string, name: string, booked: int, capacity: int}>}
     */
    public function exampleSchedule(): array
    {
        $lesson = fn (string $time, string $name, int $booked, int $capacity): array => compact('time', 'name', 'booked', 'capacity');

        return match ($this) {
            self::DanceSchool => ['club' => 'Dansstudio Utrecht', 'lessons' => [
                $lesson('17:00', 'Ballet 6-8 jaar', 14, 16),
                $lesson('18:30', 'Hiphop tieners', 18, 20),
                $lesson('20:00', 'Salsa gevorderden', 24, 24),
            ]],
            self::PersonalTrainer => ['club' => 'PT Studio Rotterdam', 'lessons' => [
                $lesson('07:00', 'PT-sessie', 1, 1),
                $lesson('12:30', 'Duo-training', 1, 2),
                $lesson('18:00', 'Small group training', 4, 6),
            ]],
            self::BoutiqueFitness => ['club' => 'Flow Studio Amsterdam', 'lessons' => [
                $lesson('07:00', 'Reformer pilates', 10, 10),
                $lesson('12:15', 'Power yoga', 17, 20),
                $lesson('19:30', 'Spinning', 24, 28),
            ]],
            self::FitnessClub => ['club' => 'FitHub Eindhoven', 'lessons' => [
                $lesson('09:00', 'Bodypump', 21, 25),
                $lesson('12:00', 'Core & stretch', 12, 15),
                $lesson('18:30', 'Circuittraining', 20, 20),
            ]],
            self::BoxingSchool => ['club' => 'Boxing Gym Den Haag', 'lessons' => [
                $lesson('16:30', 'Jeugdboksen', 15, 18),
                $lesson('19:00', 'Kickboksen beginners', 20, 24),
                $lesson('20:30', 'Sparring gevorderden', 12, 12),
            ]],
        };
    }

    /**
     * Problems this type of business recognises, each with how SquadPass solves it.
     *
     * @return list<array{title: string, text: string}>
     */
    public function painPoints(): array
    {
        return match ($this) {
            self::DanceSchool => [
                ['title' => __('A spreadsheet per class group'), 'text' => __('Who is in which group, who has paid and who stops after this season? SquadPass keeps it in one overview.')],
                ['title' => __('Absences via WhatsApp'), 'text' => __('Students report their own absence in the member portal. You see in advance who is coming, without chasing messages.')],
                ['title' => __('Chasing fees'), 'text' => __('With automatic direct debit via Mollie the fee is collected every month, and you instantly see which charge failed.')],
            ],
            self::PersonalTrainer => [
                ['title' => __('Appointments via loose messages'), 'text' => __('Clients book a session in your schedule themselves. No more back and forth about when suits.')],
                ['title' => __('Paper punch cards'), 'text' => __('Every session is deducted automatically at check-in. You and your client always see how many sessions are left.')],
                ['title' => __('Invoicing in the evenings'), 'text' => __('Clients pay online via iDEAL or direct debit, and with Moneybird every payment is ready as a draft invoice right away.')],
            ],
            self::BoutiqueFitness => [
                ['title' => __('Empty spots in class'), 'text' => __('Members book and cancel themselves in the portal or app, so freed spots are available again right away.')],
                ['title' => __('Tracking credits in Excel'), 'text' => __('Memberships with a set number of classes per month and strip cards are updated automatically with every booking or check-in.')],
                ['title' => __('A separate tool for everything'), 'text' => __('Schedule, member management, payments and invoices live in one platform for one fixed monthly price.')],
            ],
            self::FitnessClub => [
                ['title' => __('Failed direct debits'), 'text' => __('You instantly see which direct debits failed and for which member, so no revenue leaks away unnoticed.')],
                ['title' => __('Outdated software'), 'text' => __('A modern platform that works on every device, for you, your staff and your members.')],
                ['title' => __('Switching seems like a lot of work'), 'text' => __('Upload your member list as a CSV and your members are in within minutes, including membership numbers.')],
            ],
            self::BoxingSchool => [
                ['title' => __('Overcrowded sessions'), 'text' => __('Set a maximum per session. Members reserve their spot, so every session stays safe and easy to coach.')],
                ['title' => __('No idea who trains'), 'text' => __('With QR check-in you see exactly who attended, even at busy sessions.')],
                ['title' => __('Loose forms and signatures'), 'text' => __('Let members fill in and sign an enrollment and health declaration online before they step into the ring.')],
            ],
        };
    }

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function features(): array
    {
        return match ($this) {
            self::DanceSchool => [
                ['icon' => 'calendar-days', 'title' => __('Fixed class groups'), 'text' => __('Plan a season or course with fixed spots and a maximum number of students per group.')],
                ['icon' => 'ticket', 'title' => __('Trial and drop-in classes'), 'text' => __('Let new dancers book a single class, or sell a strip card for those who want to dance flexibly.')],
                ['icon' => 'map-pin', 'title' => __('Studios and teachers'), 'text' => __('Link every class to a studio and teacher, so everyone knows where to be and with whom.')],
                ['icon' => 'clipboard-document-check', 'title' => __('Enrollment forms'), 'text' => __('Let new students or their parents fill in and sign a form online, including consent for photos.')],
                ['icon' => 'credit-card', 'title' => __('Monthly or yearly subscriptions'), 'text' => __('Offer subscriptions with a set number of classes per period, or unlimited dancing.')],
                ['icon' => 'qr-code', 'title' => __('Check in at class'), 'text' => __('Students check in with their QR code, so you see exactly who attended.')],
            ],
            self::PersonalTrainer => [
                ['icon' => 'calendar-days', 'title' => __('Online booking'), 'text' => __('Open your available sessions and let clients book themselves, with a maximum per session for duo or small group training.')],
                ['icon' => 'ticket', 'title' => __('Strip cards and packages'), 'text' => __('Sell a 10-session card or a package of 12 sessions, with a validity you decide.')],
                ['icon' => 'clipboard-document-check', 'title' => __('Intake and health questions'), 'text' => __('Let new clients fill in and digitally sign an intake form in advance. Everything is stored securely.')],
                ['icon' => 'credit-card', 'title' => __('Fixed monthly plans'), 'text' => __('Working with fixed programmes? Offer a subscription with a number of sessions per month and let payment run automatically.')],
                ['icon' => 'device-phone-mobile', 'title' => __('Your own app'), 'text' => __('With the branded app your brand sits on your clients\' home screen, including push notifications.')],
                ['icon' => 'chart-bar', 'title' => __('Insight into your revenue'), 'text' => __('See per month how many clients you have and what you earn, without keeping spreadsheets yourself.')],
            ],
            self::BoutiqueFitness => [
                ['icon' => 'calendar-days', 'title' => __('Class booking'), 'text' => __('Members see the schedule, book a spot and instantly see how many spots are left.')],
                ['icon' => 'ticket', 'title' => __('Credits and strip cards'), 'text' => __('Sell intro packs and 10-class cards that members buy online and can use right away.')],
                ['icon' => 'credit-card', 'title' => __('Unlimited or per number of classes'), 'text' => __('Offer memberships with unlimited classes or a set number per month, billed monthly or yearly.')],
                ['icon' => 'map-pin', 'title' => __('Studios and instructors'), 'text' => __('Plan classes per studio and instructor, with its own capacity per class.')],
                ['icon' => 'qr-code', 'title' => __('Fast check-in'), 'text' => __('Members scan their QR code on arrival. The check-in desk instantly shows whether their membership or strip card is valid.')],
                ['icon' => 'device-phone-mobile', 'title' => __('Your studio in the App Store'), 'text' => __('With the branded app members book their class in an app with your logo and colours.')],
            ],
            self::FitnessClub => [
                ['icon' => 'users', 'title' => __('Member management'), 'text' => __('Every member\'s memberships, visits and payments in one place, quick to search.')],
                ['icon' => 'credit-card', 'title' => __('Automatic direct debit'), 'text' => __('Membership fees are collected automatically by SEPA direct debit with the Mollie integration.')],
                ['icon' => 'qr-code', 'title' => __('Check-in desk'), 'text' => __('Members check in with their QR code and the desk instantly sees whether the membership is valid.')],
                ['icon' => 'calendar-days', 'title' => __('Group classes'), 'text' => __('Plan group classes with maximum capacity and let members reserve their own spot.')],
                ['icon' => 'receipt-percent', 'title' => __('Invoices via Moneybird'), 'text' => __('Every payment automatically becomes a draft invoice in your own Moneybird administration.')],
                ['icon' => 'chart-bar', 'title' => __('Insight into growth'), 'text' => __('Follow new members, active members and revenue per month from your dashboard.')],
            ],
            self::BoxingSchool => [
                ['icon' => 'calendar-days', 'title' => __('Sessions per level'), 'text' => __('Plan youth, beginner and advanced sessions with their own capacity and coach.')],
                ['icon' => 'clipboard-document-check', 'title' => __('Digital declarations'), 'text' => __('Health questions, consent and signature in one online form, stored securely.')],
                ['icon' => 'credit-card', 'title' => __('Fees by direct debit'), 'text' => __('Monthly fees are collected automatically by SEPA direct debit, and you see failed payments right away.')],
                ['icon' => 'ticket', 'title' => __('Trial sessions and strip cards'), 'text' => __('Let new fighters book a single session, or sell a strip card for those who can\'t come every week.')],
                ['icon' => 'qr-code', 'title' => __('Check in at training'), 'text' => __('Members check in with their QR code; you instantly see whether their membership is valid.')],
                ['icon' => 'users', 'title' => __('Coaches and volunteers'), 'text' => __('Give coaches and volunteers their own account, at no extra cost per user.')],
            ],
        };
    }

    /**
     * Questions specific to this type of business. The general questions
     * (trial, contract, import) are added on the page.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqs(): array
    {
        return match ($this) {
            self::DanceSchool => [
                ['question' => __('Can I work with seasons and fixed class groups?'), 'answer' => __('Yes. You plan recurring classes with fixed enrollment, so students keep their spot in the group all season. You can also open single classes for trial or make-up lessons.')],
                ['question' => __('Can students report absence for a class themselves?'), 'answer' => __('Yes, students report their absence via the member portal or the app. You and your teachers see in advance who is coming.')],
                ['question' => __('Does SquadPass also work for a dance school with several studios?'), 'answer' => __('Absolutely. You add studios and teachers and link every class to them. Staff get their own account at no extra cost.')],
            ],
            self::PersonalTrainer => [
                ['question' => __('Is SquadPass suitable if I work alone?'), 'answer' => __('Yes. You use every feature as an independent trainer too. When you grow and colleagues join, you add them at no extra cost.')],
                ['question' => __('Can I sell both single sessions and packages?'), 'answer' => __('Absolutely. You combine strip cards, subscriptions with a set number of sessions per period and single sessions exactly the way you work.')],
                ['question' => __('How do my clients pay?'), 'answer' => __('With the Mollie integration clients pay online via iDEAL or card, and recurring amounts are collected automatically by direct debit. The money goes straight to your own account.')],
            ],
            self::BoutiqueFitness => [
                ['question' => __('Can members book a class themselves?'), 'answer' => __('Yes. Members book their spot in the member portal or in your own app. Once a class is full, nobody else can join.')],
                ['question' => __('Can I sell intro offers?'), 'answer' => __('Absolutely. Create for example a three-class strip card for new members. With the Mollie integration they buy it online and can book straight away.')],
                ['question' => __('What does SquadPass cost for my studio?'), 'answer' => __('You pay one fixed monthly price based on your number of members, with every feature included. Add-ons such as online payments you only switch on when you need them.')],
            ],
            self::FitnessClub => [
                ['question' => __('How do I switch from my current software?'), 'answer' => __('Export your member list as a CSV and upload it to SquadPass. You map the columns yourself, membership numbers are kept and you choose whether members get an invitation right away.')],
                ['question' => __('Can my staff log in too?'), 'answer' => __('Yes, with unlimited staff and trainers at no extra cost. Everyone gets their own account with the permissions their role needs.')],
                ['question' => __('What if a direct debit fails?'), 'answer' => __('You get an overview of failed direct debits per member, so you can take action right away.')],
            ],
            self::BoxingSchool => [
                ['question' => __('Is SquadPass also suitable for kickboxing, MMA or judo?'), 'answer' => __('Yes. SquadPass works for any martial art with classes, sessions and members. You decide the names, levels and times of your sessions.')],
                ['question' => __('Can I have members sign a health declaration?'), 'answer' => __('Absolutely. With the form builder you create your own declaration with health questions, consent and a digital signature.')],
                ['question' => __('Can members report absence for a session?'), 'answer' => __('Yes, via the member portal or the app. That way you and your coaches know in advance how many fighters are coming.')],
            ],
        };
    }

    public function signupTitle(): string
    {
        return match ($this) {
            self::DanceSchool => __('Ready to grow your dance school?'),
            self::PersonalTrainer => __('Ready to win back your time?'),
            self::BoutiqueFitness => __('Ready to fill your classes?'),
            self::FitnessClub => __('Ready to run your gym smarter?'),
            self::BoxingSchool => __('Ready for the next round?'),
        };
    }
}
