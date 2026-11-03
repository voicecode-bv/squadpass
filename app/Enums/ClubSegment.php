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
    case YogaStudio = 'yogastudio';
    case PilatesStudio = 'pilatesstudio';
    case CrossfitBox = 'crossfit-box';
    case Bootcamp = 'bootcamp';

    public function label(): string
    {
        return match ($this) {
            self::DanceSchool => __('Dance schools'),
            self::PersonalTrainer => __('Personal trainers'),
            self::BoutiqueFitness => __('Boutique fitness studios'),
            self::FitnessClub => __('Gyms'),
            self::BoxingSchool => __('Boxing schools'),
            self::YogaStudio => __('Yoga studios'),
            self::PilatesStudio => __('Pilates studios'),
            self::CrossfitBox => __('CrossFit boxes'),
            self::Bootcamp => __('Bootcamps'),
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
            self::YogaStudio => __('Class booking, class passes and teachers.'),
            self::PilatesStudio => __('Small groups, reformer classes and credits.'),
            self::CrossfitBox => __('WOD schedule, memberships and check-in.'),
            self::Bootcamp => __('Outdoor sessions, strip cards and trial sessions.'),
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
            self::YogaStudio => 'leaf',
            self::PilatesStudio => 'heart',
            self::CrossfitBox => 'trophy',
            self::Bootcamp => 'dumbbell',
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
            self::YogaStudio => __('Software for yoga studios: class booking, class passes and payments'),
            self::PilatesStudio => __('Software for pilates studios: reformer classes, credits and memberships'),
            self::CrossfitBox => __('Software for CrossFit boxes: WOD schedule, memberships and check-in'),
            self::Bootcamp => __('Software for bootcamps: sessions, strip cards and payments'),
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
            self::YogaStudio => __('Let students book yoga classes themselves, sell class passes and memberships and collect payments automatically with SquadPass. :days days free, cancel monthly.', $days),
            self::PilatesStudio => __('Fill every reformer and mat class: clients book their spot, pay with credits or a membership and check in at the door. SquadPass for pilates studios. :days days free.', $days),
            self::CrossfitBox => __('Plan your WODs and classes, collect memberships by direct debit and let athletes check in with a QR code. SquadPass for CrossFit boxes. :days days free.', $days),
            self::Bootcamp => __('Plan outdoor sessions, sell strip cards and let new participants book a trial session online. SquadPass for bootcamps and outdoor training. :days days free.', $days),
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
            self::YogaStudio => __('For yoga studios'),
            self::PilatesStudio => __('For pilates studios'),
            self::CrossfitBox => __('For CrossFit boxes'),
            self::Bootcamp => __('For bootcamps'),
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
            self::YogaStudio => [__('More time on the mat,'), __('less time behind the screen.')],
            self::PilatesStudio => [__('Every reformer booked,'), __('without the spreadsheet.')],
            self::CrossfitBox => [__('Your box runs on strength,'), __('your admin on autopilot.')],
            self::Bootcamp => [__('Train outside,'), __('let the admin run itself.')],
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
            self::YogaStudio => __('From vinyasa to yin: students book their own spot, pay with a class pass or membership and get a reminder before class. You keep the calm, SquadPass keeps the overview.'),
            self::PilatesStudio => __('Reformer and mat classes have limited spots. With SquadPass clients book and cancel themselves, credits are tracked automatically and you see at a glance which classes are full.'),
            self::CrossfitBox => __('Put your WODs and classes in the schedule, collect memberships automatically and let athletes check in with their QR code. Your coaches focus on coaching, not on paperwork.'),
            self::Bootcamp => __('Whether you train in the park or on the beach: participants book their session online, pay with a strip card or subscription and get a reminder beforehand.'),
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
            self::YogaStudio => ['club' => 'Yoga Loft Haarlem', 'lessons' => [
                $lesson('07:30', 'Vinyasa flow', 16, 18),
                $lesson('12:15', 'Lunch yoga', 9, 14),
                $lesson('20:00', 'Yin yoga', 18, 18),
            ]],
            self::PilatesStudio => ['club' => 'Core Pilates Leiden', 'lessons' => [
                $lesson('08:00', 'Reformer basis', 8, 8),
                $lesson('12:30', 'Mat pilates', 11, 15),
                $lesson('19:00', 'Reformer advanced', 6, 8),
            ]],
            self::CrossfitBox => ['club' => 'CrossFit Groningen', 'lessons' => [
                $lesson('06:30', 'WOD', 14, 16),
                $lesson('12:00', 'Olympic lifting', 8, 10),
                $lesson('18:30', 'WOD', 16, 16),
            ]],
            self::Bootcamp => ['club' => 'Bootcamp Vondelpark', 'lessons' => [
                $lesson('07:00', 'Ochtendbootcamp', 18, 20),
                $lesson('12:30', 'Lunch bootcamp', 9, 15),
                $lesson('19:00', 'Bootcamp intensief', 20, 20),
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
            self::YogaStudio => [
                ['title' => __('Booking via direct messages'), 'text' => __('Students book their spot in the schedule themselves, and you see in advance how full every class is.')],
                ['title' => __('Paper class cards'), 'text' => __('Class passes are digital and every booking is deducted automatically. Students always see how many classes they have left.')],
                ['title' => __('Late cancellations'), 'text' => __('Set a cancellation deadline: whoever cancels too late keeps the class used, so freed spots go to someone else in time.')],
            ],
            self::PilatesStudio => [
                ['title' => __('Waiting for a free reformer'), 'text' => __('Set the number of reformers as the capacity. Once a class is full nobody else can book, and freed spots are available again right away.')],
                ['title' => __('Credits in a notebook'), 'text' => __('Credits from strip cards and memberships are updated automatically with every booking and cancellation.')],
                ['title' => __('Intake on paper'), 'text' => __('New clients fill in an intake with health questions online and sign it digitally before their first class.')],
            ],
            self::CrossfitBox => [
                ['title' => __('Overcrowded WODs'), 'text' => __('Set a maximum per class so every athlete gets attention from the coach. Members reserve their spot themselves.')],
                ['title' => __('Chasing membership fees'), 'text' => __('Memberships are collected automatically by direct debit, and you see straight away which charge failed.')],
                ['title' => __('No idea who trains'), 'text' => __('With QR check-in you see exactly who attended, and your reports show attendance per class.')],
            ],
            self::Bootcamp => [
                ['title' => __('Group apps for every session'), 'text' => __('Participants book in the schedule themselves and get an email reminder, without you posting in a group chat.')],
                ['title' => __('Cash and payment requests'), 'text' => __('Strip cards and subscriptions are paid online via iDEAL or collected by direct debit.')],
                ['title' => __('New people who never show up'), 'text' => __('Let newcomers book a trial session online. You get their details right away and convert them into a member with one click.')],
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
            self::YogaStudio => [
                ['icon' => 'calendar-days', 'title' => __('Class schedule'), 'text' => __('Plan recurring classes per room and teacher, each with its own number of spots.')],
                ['icon' => 'ticket', 'title' => __('Class passes'), 'text' => __('Sell 5- or 10-class passes online. Every booking is deducted automatically.')],
                ['icon' => 'credit-card', 'title' => __('Memberships'), 'text' => __('Offer unlimited yoga or a set number of classes per month, collected by direct debit.')],
                ['icon' => 'user-plus', 'title' => __('Trial classes'), 'text' => __('New students book a free or paid trial class on your trial page.')],
                ['icon' => 'bell', 'title' => __('Class reminders'), 'text' => __('Students get an email before their class, so fewer spots go unused.')],
                ['icon' => 'sparkles', 'title' => __('Your own style'), 'text' => __('Your logo and colours in the member portal, on your pages and in every email.')],
            ],
            self::PilatesStudio => [
                ['icon' => 'users', 'title' => __('Small groups'), 'text' => __('Set the capacity per class to the number of reformers or mats you have.')],
                ['icon' => 'ticket', 'title' => __('Credits and strip cards'), 'text' => __('Sell intro packs and 10-class cards that clients buy online and use right away.')],
                ['icon' => 'clock', 'title' => __('Cancellation deadline'), 'text' => __('Clients cancel themselves up to your deadline; late cancellations keep the credit used.')],
                ['icon' => 'clipboard-document-check', 'title' => __('Intake and health questions'), 'text' => __('Let new clients fill in and digitally sign an intake form in advance. Everything is stored securely.')],
                ['icon' => 'credit-card', 'title' => __('Memberships'), 'text' => __('Monthly or yearly memberships with a set number of classes, collected automatically.')],
                ['icon' => 'chart-bar', 'title' => __('Occupancy per class'), 'text' => __('See which classes fill up and which have room, so you plan your schedule better.')],
            ],
            self::CrossfitBox => [
                ['icon' => 'calendar-days', 'title' => __('WOD and class schedule'), 'text' => __('Plan WODs, open gym and specialty classes with their own capacity and coach.')],
                ['icon' => 'credit-card', 'title' => __('Memberships by direct debit'), 'text' => __('Monthly memberships are collected automatically by SEPA direct debit with the Mollie integration.')],
                ['icon' => 'qr-code', 'title' => __('QR check-in'), 'text' => __('Athletes check in with their QR code at the desk or on a tablet at the entrance.')],
                ['icon' => 'ticket', 'title' => __('Drop-ins and strip cards'), 'text' => __('Sell drop-in visits and strip cards for athletes who train flexibly.')],
                ['icon' => 'users', 'title' => __('Coaches with their own account'), 'text' => __('Give every coach access to the schedule and check-in, at no extra cost per user.')],
                ['icon' => 'chart-bar', 'title' => __('Reports'), 'text' => __('Follow members, churn, revenue and attendance per class over the past months.')],
            ],
            self::Bootcamp => [
                ['icon' => 'map-pin', 'title' => __('Sessions per location'), 'text' => __('Plan sessions per park or location, with a maximum number of participants per group.')],
                ['icon' => 'ticket', 'title' => __('Strip cards'), 'text' => __('Sell digital strip cards that participants buy online and use for any session.')],
                ['icon' => 'user-plus', 'title' => __('Trial sessions'), 'text' => __('Share your trial page and newcomers book a free or paid trial session themselves.')],
                ['icon' => 'bell', 'title' => __('Reminders'), 'text' => __('Participants get an email before every session, so you know who is coming.')],
                ['icon' => 'credit-card', 'title' => __('Subscriptions'), 'text' => __('Offer unlimited training or a set number of sessions per month, collected automatically.')],
                ['icon' => 'device-phone-mobile', 'title' => __('Booking on any phone'), 'text' => __('Participants book and cancel from their phone in the member portal.')],
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
            self::YogaStudio => [
                ['question' => __('Can students book a class themselves?'), 'answer' => __('Yes. Students book their spot in the member portal and see how many spots are left. Once a class is full, nobody else can join.')],
                ['question' => __('Can I sell class passes and memberships side by side?'), 'answer' => __('Absolutely. You combine class passes, memberships with unlimited or a set number of classes, and single classes exactly the way your studio works.')],
                ['question' => __('Can I work with several teachers and rooms?'), 'answer' => __('Yes. You add teachers and rooms and link every class to them. Teachers get their own account at no extra cost.')],
            ],
            self::PilatesStudio => [
                ['question' => __('Can I limit a class to the number of reformers?'), 'answer' => __('Yes. You set the capacity per class, for example eight reformers. Once all spots are booked, the class is full.')],
                ['question' => __('What happens if a client cancels too late?'), 'answer' => __('You choose the cancellation deadline yourself. Whoever cancels before it gets their credit back; whoever cancels later keeps it used.')],
                ['question' => __('Can I sell intro packs?'), 'answer' => __('Absolutely. Create for example a three-class strip card for new clients. With the Mollie integration they buy it online and can book straight away.')],
            ],
            self::CrossfitBox => [
                ['question' => __('Can athletes reserve their spot in a WOD?'), 'answer' => __('Yes. Athletes book their class in the member portal, and you set a maximum per class so the coach can keep an eye on everyone.')],
                ['question' => __('How do drop-in visitors pay?'), 'answer' => __('Sell a drop-in or strip card that visitors buy online via iDEAL or card with the Mollie integration.')],
                ['question' => __('Can coaches check athletes in?'), 'answer' => __('Yes. Coaches get their own account with access to the check-in desk, and you decide per person which parts they may use.')],
            ],
            self::Bootcamp => [
                ['question' => __('Does SquadPass work for outdoor training?'), 'answer' => __('Yes. You add your locations, such as a park or beach, and link every session to them. Participants see where to be when they book.')],
                ['question' => __('Can newcomers try a session first?'), 'answer' => __('Yes. Share your trial page and newcomers book a free or paid trial session themselves. You get their details right away.')],
                ['question' => __('Can participants book from their phone?'), 'answer' => __('Absolutely. The member portal works on every phone, and with the branded app add-on participants book in your own app.')],
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
            self::YogaStudio => __('Ready to give your studio some breathing space?'),
            self::PilatesStudio => __('Ready to fill every reformer?'),
            self::CrossfitBox => __('Ready to level up your box?'),
            self::Bootcamp => __('Ready to grow your bootcamp?'),
        };
    }
}
