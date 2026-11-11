<?php

namespace App\Enums;

/**
 * Product features with their own landing page. The value is the Dutch URL
 * slug, so the pages rank on what club owners search for
 * (/functies/ledenadministratie, /functies/automatische-incasso).
 */
enum MarketingFeature: string
{
    case MemberManagement = 'ledenadministratie';
    case LessonSchedule = 'lesrooster';
    case StripCards = 'strippenkaart';
    case DirectDebit = 'automatische-incasso';
    case CheckIn = 'check-in';
    case TrialLessons = 'proefles';
    case Forms = 'digitale-formulieren';
    case WhiteLabel = 'white-label';

    public function label(): string
    {
        return match ($this) {
            self::MemberManagement => __('Member administration'),
            self::LessonSchedule => __('Lesson schedule & booking'),
            self::StripCards => __('Digital strip cards'),
            self::DirectDebit => __('Subscriptions & direct debit'),
            self::CheckIn => __('QR check-in'),
            self::TrialLessons => __('Trial lessons'),
            self::Forms => __('Digital forms'),
            self::WhiteLabel => __('White label'),
        };
    }

    /**
     * One line for the overview cards that link to the feature pages.
     */
    public function cardText(): string
    {
        return match ($this) {
            self::MemberManagement => __('All members, memberships and payments in one place.'),
            self::LessonSchedule => __('Recurring lessons that members book themselves.'),
            self::StripCards => __('Sell strip cards online, deducted at every visit.'),
            self::DirectDebit => __('Monthly or yearly plans, collected automatically.'),
            self::CheckIn => __('Members scan in, you see right away who is valid.'),
            self::TrialLessons => __('Let new people book a trial lesson online.'),
            self::Forms => __('Intake, declarations and signatures online.'),
            self::WhiteLabel => __('Your logo and colours throughout the platform.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MemberManagement => 'users',
            self::LessonSchedule => 'calendar-days',
            self::StripCards => 'ticket',
            self::DirectDebit => 'credit-card',
            self::CheckIn => 'qr-code',
            self::TrialLessons => 'user-plus',
            self::Forms => 'clipboard-document-check',
            self::WhiteLabel => 'sparkles',
        };
    }

    public function metaTitle(): string
    {
        return match ($this) {
            self::MemberManagement => __('Member management software for sports clubs and studios'),
            self::LessonSchedule => __('Online lesson schedule and booking system for your club'),
            self::StripCards => __('Digital strip card for your studio or club'),
            self::DirectDebit => __('Automatic direct debit for your sports club or gym'),
            self::CheckIn => __('Check-in system with QR code for gyms and studios'),
            self::TrialLessons => __('Let new members book a trial lesson online'),
            self::Forms => __('Digital forms with signature for your club or studio'),
            self::WhiteLabel => __('White label club software with your own logo and colours'),
        };
    }

    public function metaDescription(): string
    {
        $days = ['days' => ClubPlan::TRIAL_DAYS];

        return match ($this) {
            self::MemberManagement => __('Keep your member administration in one place: members, membership numbers, subscriptions, payments and visits. Import your member list via CSV. Try SquadPass free for :days days.', $days),
            self::LessonSchedule => __('Plan recurring lessons with a maximum capacity and let members book and cancel online. Automatic reminders before every lesson. Try SquadPass free for :days days.', $days),
            self::StripCards => __('Replace paper punch cards with a digital strip card. Members buy online via iDEAL, and every booking is deducted automatically. Try SquadPass free for :days days.', $days),
            self::DirectDebit => __('Collect membership fees automatically with SEPA direct debit via Mollie. Monthly or yearly subscriptions, failed payment alerts and draft invoices in Moneybird. Try SquadPass free for :days days.', $days),
            self::CheckIn => __('Let members check in with their personal QR code at the desk or on a tablet. See right away whether their subscription or strip card is valid. Try SquadPass free for :days days.', $days),
            self::TrialLessons => __('Put a trial lesson page on your website: visitors book a free or paid trial lesson themselves, and you convert them into members with one click. Try SquadPass free for :days days.', $days),
            self::Forms => __('Build your own intake, health declaration or registration form and let members fill it in and sign online. Everything stored with the member. Try SquadPass free for :days days.', $days),
            self::WhiteLabel => __('Offer SquadPass under your own brand: your logo and brand colour in the app, member portal, sign-up pages and emails. Included in every plan. Try free for :days days.', $days),
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
            self::MemberManagement => [__('Your member administration,'), __('finally in one place.')],
            self::LessonSchedule => [__('Full lessons,'), __('no more messages back and forth.')],
            self::StripCards => [__('Goodbye punch card,'), __('hello digital strip card.')],
            self::DirectDebit => [__('Membership fees collected,'), __('without chasing anyone.')],
            self::CheckIn => [__('Checked in within a second,'), __('without a queue at the desk.')],
            self::TrialLessons => [__('From visitor to member,'), __('without the paperwork.')],
            self::Forms => [__('No more paper forms,'), __('everything signed online.')],
            self::WhiteLabel => [__('Your club,'), __('your brand.')],
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::MemberManagement => __('Members, membership numbers, subscriptions, strip cards, payments and visits: SquadPass keeps every member\'s details together, quick to search and always up to date.'),
            self::LessonSchedule => __('Plan your schedule once, and members book, cancel and get a reminder themselves. You always see who is coming and how many spots are left.'),
            self::StripCards => __('Create strip cards with the number of visits and the price you want. Members buy them online and always see how many visits they have left.'),
            self::DirectDebit => __('Offer monthly or yearly subscriptions and let SquadPass collect them by SEPA direct debit via your own Mollie account. You see right away which payment failed.'),
            self::CheckIn => __('Every member has a personal QR code in the portal. Scan it at the desk or let members check in on a tablet, and SquadPass instantly shows whether their subscription or strip card is valid.'),
            self::TrialLessons => __('Share your trial lesson page and visitors pick a lesson and book it themselves, free or paid. You get a message for every new lead and turn them into a member with one click.'),
            self::Forms => __('Build your own forms, from an intake to a health declaration. Members fill them in and sign in the portal, and the answers are stored with their profile.'),
            self::WhiteLabel => __('Your members see your logo and your brand colour everywhere: in the member portal, on your sign-up and trial lesson pages and in every email. Only a small "Powered by SquadPass" remains.'),
        };
    }

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function benefits(): array
    {
        return match ($this) {
            self::MemberManagement => [
                ['icon' => 'users', 'title' => __('Every member in one overview'), 'text' => __('Search and filter members by status, and open a member to see their memberships, credits, payments and check-ins.')],
                ['icon' => 'arrow-down-tray', 'title' => __('Import in minutes'), 'text' => __('Upload your existing member list as a CSV. Membership numbers and member-since dates come along.')],
                ['icon' => 'identification', 'title' => __('Automatic membership numbers'), 'text' => __('New members get the next membership number automatically, or keep the number from your old system.')],
                ['icon' => 'user-plus', 'title' => __('Members sign up themselves'), 'text' => __('Share your sign-up link or QR code and new members create their own account, without you typing anything over.')],
            ],
            self::LessonSchedule => [
                ['icon' => 'calendar-days', 'title' => __('Recurring lessons'), 'text' => __('Plan a lesson once with its day, time, room and trainer, and it repeats every week.')],
                ['icon' => 'users', 'title' => __('Capacity per lesson'), 'text' => __('Set a maximum number of spots. When a lesson is full, nobody else can book.')],
                ['icon' => 'clock', 'title' => __('Cancellation deadline'), 'text' => __('Members cancel themselves up to the deadline you choose. Later cancellations keep the credit used.')],
                ['icon' => 'bell', 'title' => __('Automatic reminders'), 'text' => __('Members get an email before their lesson, at the moment you choose.')],
            ],
            self::StripCards => [
                ['icon' => 'ticket', 'title' => __('Your own cards'), 'text' => __('A 5-, 10- or 20-visit card or an intro card for new members: you decide the number of visits and the price.')],
                ['icon' => 'shopping-cart', 'title' => __('Buy online'), 'text' => __('With the Mollie add-on members buy a strip card via iDEAL or card and can book straight away.')],
                ['icon' => 'qr-code', 'title' => __('Deducted automatically'), 'text' => __('Every booking uses a credit, and the check-in desk instantly shows whether a member still has visits left.')],
                ['icon' => 'clock', 'title' => __('Credits that expire'), 'text' => __('Choose after how many days unused credits expire. Members get an email three days before.')],
            ],
            self::DirectDebit => [
                ['icon' => 'credit-card', 'title' => __('Monthly or yearly'), 'text' => __('Subscriptions with unlimited lessons or a set number per period, billed monthly or yearly.')],
                ['icon' => 'banknotes', 'title' => __('SEPA direct debit via Mollie'), 'text' => __('Members authorise once, after which every term is collected automatically.')],
                ['icon' => 'exclamation-triangle', 'title' => __('Failed payments in view'), 'text' => __('You see which charge failed for which member, and the member gets an email to sort it out.')],
                ['icon' => 'receipt-percent', 'title' => __('Invoices in Moneybird'), 'text' => __('With the Moneybird add-on every paid payment becomes a draft invoice in your own administration.')],
            ],
            self::CheckIn => [
                ['icon' => 'qr-code', 'title' => __('Personal QR code'), 'text' => __('Members show their code from the portal on their phone, no card or key fob needed.')],
                ['icon' => 'computer-desktop', 'title' => __('Self check-in on a tablet'), 'text' => __('Put a tablet at the entrance in kiosk mode and members check in themselves.')],
                ['icon' => 'check-circle', 'title' => __('Valid at a glance'), 'text' => __('The check-in desk shows straight away whether a subscription or strip card covers the visit.')],
                ['icon' => 'chart-bar', 'title' => __('Insight into attendance'), 'text' => __('See who attended which lesson, and follow attendance and no-shows in your reports.')],
            ],
            self::TrialLessons => [
                ['icon' => 'link', 'title' => __('Your own trial lesson page'), 'text' => __('One link for your website, Instagram or flyer, with the lessons you open for trial lessons.')],
                ['icon' => 'banknotes', 'title' => __('Free or paid'), 'text' => __('Offer trial lessons for free or ask a small fee that visitors pay online via iDEAL.')],
                ['icon' => 'bell', 'title' => __('A message for every lead'), 'text' => __('You and your staff get an email with the details of every new trial booking.')],
                ['icon' => 'user-plus', 'title' => __('Convert with one click'), 'text' => __('Was it a match? Turn the trial member into a member, and follow your conversion in the reports.')],
            ],
            self::Forms => [
                ['icon' => 'pencil-square', 'title' => __('Your own questions'), 'text' => __('Compose your own forms, from short answers to multiple choice, plus a digital signature.')],
                ['icon' => 'clipboard-document-check', 'title' => __('Digital signature'), 'text' => __('Members sign on their phone or computer, and the signature is stored with the form.')],
                ['icon' => 'envelope', 'title' => __('Sent to the right member'), 'text' => __('Assign a form to a member and they get an email with a direct link to fill it in.')],
                ['icon' => 'document-text', 'title' => __('Answers with the member'), 'text' => __('All answers are kept with the member, so you can look them up any time.')],
            ],
            self::WhiteLabel => [
                ['icon' => 'photo', 'title' => __('Logo for light and dark mode'), 'text' => __('Upload your logo, with a separate version for dark mode if you like.')],
                ['icon' => 'sparkles', 'title' => __('Your brand colour'), 'text' => __('Choose your colour and every button, menu and chart follows. Light colours are adjusted so text stays readable.')],
                ['icon' => 'envelope', 'title' => __('Emails in your style'), 'text' => __('Lesson reminders, payment emails and news reach your members with your logo and colour.')],
                ['icon' => 'device-phone-mobile', 'title' => __('Your own app'), 'text' => __('Go one step further with your own app in the App Store and Google Play.')],
            ],
        };
    }

    /**
     * Questions specific to this feature. The general questions (trial,
     * contract) are added on the page.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqs(): array
    {
        return match ($this) {
            self::MemberManagement => [
                ['question' => __('Can staff work in the member administration too?'), 'answer' => __('Yes. Add unlimited staff and decide per person which parts they may use, such as members, billing or reports.')],
                ['question' => __('Can members update their own details?'), 'answer' => __('Yes. Members manage their own name, email address and password in their account, so your administration stays up to date.')],
            ],
            self::LessonSchedule => [
                ['question' => __('Can members book through an app?'), 'answer' => __('Members book in the member portal on any device. With the branded app add-on they also book in your own app in the App Store and Google Play.')],
                ['question' => __('Can I cancel a single lesson?'), 'answer' => __('Yes. You cancel one lesson without changing the rest of the schedule, for example on a public holiday.')],
            ],
            self::StripCards => [
                ['question' => __('What happens when a member cancels a lesson?'), 'answer' => __('When a member cancels before the cancellation deadline, the credit goes back to their strip card. After the deadline the credit is used.')],
                ['question' => __('Where does the money go?'), 'answer' => __('Payments go straight to your own Mollie account. SquadPass never holds your money.')],
            ],
            self::DirectDebit => [
                ['question' => __('Do I need a Mollie account?'), 'answer' => __('Yes, for online payments and direct debit you connect your own Mollie account. The money goes straight to you, and you switch on the Mollie add-on when you need it.')],
                ['question' => __('Which payment methods can members use?'), 'answer' => __('Members pay via iDEAL or card through Mollie, and recurring terms are collected by SEPA direct debit.')],
            ],
            self::CheckIn => [
                ['question' => __('Do I need special hardware?'), 'answer' => __('No. The check-in desk and kiosk run in the browser on a tablet, laptop or phone you already have.')],
                ['question' => __('Can trial visitors check in too?'), 'answer' => __('Yes. Someone with a booked trial lesson can be checked in for that lesson without a subscription or strip card.')],
            ],
            self::TrialLessons => [
                ['question' => __('Does a trial lesson count towards my member limit?'), 'answer' => __('No. Trial members only count once you convert them into a member.')],
                ['question' => __('Can someone book more than one trial lesson?'), 'answer' => __('No, every person can book one trial lesson per club, so the offer stays meant for newcomers.')],
            ],
            self::Forms => [
                ['question' => __('Who can see the answers?'), 'answer' => __('Only staff with permission for forms can see the answers. You decide this per staff member.')],
                ['question' => __('Which forms do clubs use most?'), 'answer' => __('Intake forms, health declarations, consent for photos and registration forms for new members.')],
            ],
            self::WhiteLabel => [
                ['question' => __('Does white label cost extra?'), 'answer' => __('No, your logo and brand colour are included in every plan. Only your own app is an add-on.')],
                ['question' => __('Can I remove the SquadPass name completely?'), 'answer' => __('A small "Powered by SquadPass" line stays under your logo. Everything else carries your brand.')],
            ],
        };
    }
}
