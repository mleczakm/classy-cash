![Classy Cash - Nowoczesne zarządzanie finansami dla skrbnika klasowego](assets/images/classy-cash-banner.jpg)


**Classy Cash** to nowoczesna aplikacja webowa stworzona z myślą o skarbnikach szkolnych. Jej zadaniem jest automatyzacja zbierania składek, monitorowanie wpłat oraz ułatwienie rozliczeń z rodzicami i uczniami.

## 🚀 Szybki start (Docker)

Aplikacja jest dostarczana jako obraz kontenerowy, co zapewnia powtarzalność środowiska. Do poprawnego działania wymagana jest baza danych **PostgreSQL**.

### Zmienne środowiskowe
Przed uruchomieniem upewnij się, że przekazałeś niezbędne parametry połączenia:

* `DATABASE_URL`: DSN do bazy PostgreSQL (np. `postgresql://user:password@db:5432/classy_cash?serverVersion=16&charset=utf8`)
* `APP_SECRET`: Unikalny ciąg znaków dla bezpieczeństwa sesji Symfony.
* `MAILER_DSN`: Konfiguracja wysyłki wiadomości e-mail (wspierane wszystkie formaty DSN zgodne z Symfony Mailer).

### Przykładowe uruchomienie
```bash
docker run -d \
  --name classy-cash \
  -e DATABASE_URL="postgresql://db_user:db_pass@host:5432/db_name" \
  -e MAILER_DSN="smtp://user:pass@smtp.example.com:587" \
  -p 8080:80 \
  mleczakm/classy-cash:latest
```

---

## 🛠️ Pierwsza konfiguracja (Onboarding)

Aplikacja została zaprojektowana tak, aby proces wdrożenia był jak najprostszy. Po pierwszym uruchomieniu zostaniesz przeprowadzony przez dwa kluczowe etapy:

### 1. Tworzenie Administratora
Przy pierwszym wejściu na stronę główną, system automatycznie przekieruje Cię do ekranu **rejestracji pierwszego użytkownika**. To konto otrzyma pełne uprawnienia administracyjne.

### 2. Panel Konfiguracyjny
Po pierwszym zalogowaniu się administratora, wyświetlony zostanie ekran konfiguracji wstępnej, gdzie należy ustawić:

* **Dane Płatności:**
    * Numer telefonu do otrzymywania przelewów na telefon **BLIK**.
    * **Numer rachunku bankowego** do przyjmowania standardowych przelewów tradycyjnych.
* **Automatyczne potwierdzanie płatności:**
    * Konfiguracja automatycznego księgowania (aktualnie wspierany jedynie **Alior Bank** poprzez parsowanie powiadomień e-mail na koncie **Gmail**).
    * Wymagane podanie użytkownika i hasła (zalecane hasło aplikacji) do konta Gmail.
* **Konfiguracja Mailera:**
    * Ustawienie parametrów **DSN** dla powiadomień wychodzących (system wspiera wszystkie formaty obsługiwane przez Symfony).

---

## ✨ Funkcje systemu

* ✅ **Centralny rejestr składek** – przejrzysta lista wpłat bez papierowych zeszytów.
* ✅ **Płatności mobilne** – obsługa przelewów na telefon BLIK.
* ✅ **Automatyzacja (Alior + Gmail)** – oszczędność czasu dzięki automatycznemu rozpoznawaniu wpłat z potwierdzeń mailowych.
* ✅ **Powiadomienia** – systemowa wysyłka informacji do rodziców/uczniów.

---

## 🏗️ Technologia

Projekt oparty jest o framework **Symfony** i konteneryzację **Docker**.

> **Ważne:** Aplikacja wspiera wyłącznie bazę danych **PostgreSQL**.

---

## 📄 Licencja

Projekt udostępniany jest na licencji **MIT**. Szczegóły znajdują się w pliku LICENSE.

---
*Stworzone, aby ułatwić życie każdemu skarbnikowi.* 🎓
