---
paths:
  - 'app/{Events,Livewire/CheckIns}/**'
---

# Events Livewire Check Ins

## Kioskbevestiging: Reverb via Herd, direct verstuurd en per club afgeschermd
Het kioskscherm (Livewire\CheckIns\Kiosk) houdt geen check-in-historie bij; het meldt de uitkomst van een scan. CheckInWithCode::fromClubCode dispatcht MemberCheckedIn bij succes en CheckInRefused bij een CheckInException (en gooit die daarna gewoon door, zodat de app zijn 422 houdt). Het scherm dekt de QR een paar seconden af: blauw met naam plus dekking, rood met naam plus reden (Alpine-component spCheckInBanner). Een code die niet verifieert noemt geen club, dus daar gaat niets heen — anders kon iedereen met een verzonnen code het scherm van een club beschilderen. De baliescan broadcast bewust niets: daar ziet de medewerker de toast al.

Beide events gaan via CheckInWithCode::announce(), dat Throwable afvangt en report(): een scherm dat de melding mist is cosmetisch, maar het mag nooit veranderen wat de app te horen krijgt — niet het geregistreerde bezoek en niet de weigering.

Reverb draait als Herd-service: poort 8080, door nginx gefront op https://reverb.herd.test (443) met websocket-upgrade. De credentials van die service staan vast in /Users/Shared/Herd/services/reverb/1.x/.env en horen zo in .env te staan: REVERB_APP_ID=1001, REVERB_APP_KEY=laravel-herd, REVERB_APP_SECRET=secret, REVERB_HOST=reverb.herd.test, REVERB_PORT=443, REVERB_SCHEME=https. De VITE_REVERB_*-spiegels zitten in de JS-bundle, dus na het wijzigen van een van die waarden is `npm run build` nodig. Niets zelf starten — Herd doet dat.

Beide events implementeren ShouldBroadcastNow, niet ShouldBroadcast: er draait lokaal geen queue-worker en de melding mag niet op een worker wachten. Omdat de broadcast dan binnen de check-in-request valt, staan er timeouts op de reverb-connection (connect 1s, totaal 2s). Tests met `Broadcast::shouldReceive('queue')->once()->andThrow(...)` houden vast dat een liggende Reverb noch het bezoek noch de foutmelding aan het lid kost.

Kanaal is de privéchannel clubs.{clubId}.check-ins, geautoriseerd in routes/channels.php op de manage-ability van ClubPolicy, met broadcastAs 'member.checked-in' en 'check-in.refused'. Echo en pusher-js zitten in een eigen Vite-entry (resources/js/echo.js) die alleen layouts/kiosk.blade.php laadt; die layout zet ook <meta name="csrf-token"> voor /broadcasting/auth.

Kanaalautorisatie testen: de suite draait op de null-driver die niets autoriseert. Zet broadcasting.default op reverb (dummy key/secret) én doe `require base_path('routes/channels.php')`, want channels worden per driver-instantie bij het booten geregistreerd.
