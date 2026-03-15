# 本番環境: Contact Form 7・SES・Route 53 設定手順

Lightsail 上の WordPress（company-hp）で、独自ドメイン・お問い合わせフォーム・メール送信を本番運用するための手順です。

- **Route 53**: ドメインの DNS 管理とサイトの名前解決
- **Amazon SES**: メール送信（Contact Form 7 からの送信先）
- **Contact Form 7 + WP Mail SMTP**: フォーム表示と SES 経由で送信

**アカウント分担（本手順の想定）**: **Route 53** は **infrastructure アカウント**、**CloudFront**（company-hp 用）と **ACM 証明書**（CloudFront 用）は **production アカウント** で設定します。Lightsail の WordPress インスタンスも production アカウントの想定です。**SES が infrastructure アカウントでセットアップ済み** の場合は、production の Lightsail（WordPress）から **そのまま利用** できます（「[2.0 SES が infrastructure アカウントにある場合](#20-ses-が-infrastructure-アカウントにある場合production-の-lightsail-から利用する)」参照）。

**廉価に HTTPS 化したい場合**: **方法 C（インスタンス直で Let's Encrypt）** なら **追加費用 $0** です。Lightsail の WordPress に標準で入る **bncert-tool** で Let's Encrypt 証明書を取得し、インスタンス上で HTTPS を終端します。ロードバランサーも CloudFront も不要で、**Route 53 の A は静的 IP のまま** で利用できます。詳細は「[1.4 方法 C: インスタンス直で Let's Encrypt（廉価）](#14-方法-c-インスタンス直で-lets-encrypt廉価)」を参照してください。

**構成をシンプルにしたい場合**: 設定や保守の手間を減らしたい場合は **「1.4 HTTPS」** で **方法 A（Lightsail ロードバランサー）** または **方法 C（Let's Encrypt）** を選ぶと、CloudFront・origin.axiola.jp・wp-config の細かい設定が不要になります。方法 A は月額約 $18、方法 C は追加 $0。詳細は「[1.4 方法 A・B・C の比較](#14-方法-a・b・c-の比較)」を参照してください。

---

## 前提条件

- 取得済みのドメイン（例: `example.com`）。レジストラはどこでも可（お名前.com、Route 53 ドメイン登録など）。
- Lightsail で WordPress インスタンスを作成済みで、**静的 IP** をアタッチ済み。
- 同一 AWS リージョン（例: 東京 `ap-northeast-1`）で作業する想定。
- **Route 53** は infrastructure アカウント、**CloudFront・ACM** は production アカウントで操作する前提。

---

## 1. Route 53 の設定

### 1.1 ホストゾーンの作成

**axiola.jp の場合**: すでに **infrastructure アカウント** に `axiola.jp` のホストゾーンが作成済みです。この場合は **1.1 はスキップ** し、ネームサーバー未設定なら **1.2**、すでに Route 53 に切り替え済みなら **1.3**（A レコードの追加）から進めてください。

**新規でホストゾーンを作成する場合**（上記以外のドメインなど）:

1. **AWS コンソール**（infrastructure アカウント）→ **Route 53** を開く。
2. 左メニュー **「ホストゾーン」** → **「ホストゾーンの作成」**。
3. 以下を入力して **「ホストゾーンの作成」** をクリック。
   - **ドメイン名**: 使用するドメイン（例: `example.com`）。先頭に `www` は付けない。
   - **タイプ**: **パブリックホストゾーン** のまま。
   - **説明**: 任意。

作成後、**NS（ネームサーバー）** が 4 つと **SOA** が 1 つ自動作成されます。**NS の 4 つの値**（例: `ns-123.awsdns-45.com.` など）をメモします。

### 1.2 レジストラでネームサーバーを Route 53 に変更

ドメインを取得したレジストラ（お名前.com、Google Domains、Route 53 ドメイン登録 など）の管理画面で、ネームサーバーを **Route 53 の NS 4 つ** に変更します。

- **お名前.com**: ドメイン一覧 → 対象ドメイン → 「ネームサーバーの設定」→「その他」を選び、4 つの NS を入力（末尾の `.` は不要な場合が多いです。レジストラの指示に従ってください）。
- **Route 53 でドメイン登録した場合**: 通常は自動でこのホストゾーンに紐づくため、追加作業は不要なことがあります。

反映には **数分〜最大 48〜72 時間** かかることがあります。`dig NS example.com` や [DNS チェックツール](https://dnschecker.org/) で確認できます。

### 1.3 ホストゾーンに A レコードを追加（サイトを Lightsail に向ける）

Route 53 の **「ホストゾーン」** で対象ドメインのホストゾーンを開き、以下のレコードを作成します。

**方法 B（ACM + CloudFront）でオリジン用サブドメインを使う場合** は、ルート・www に加えて **オリジン用のサブドメイン**（例: `origin.axiola.jp`）の A レコードもこの時点で作成します。CloudFront のオリジンはこの FQDN を参照するため、先に Route 53 に登録しておく必要があります。

| レコード名 | タイプ | 値 | 用途 |
|------------|--------|-----|------|
| （空欄） | A | Lightsail の静的 IP | ルートドメイン（例: `axiola.jp`）。方法 B の場合はのちに 1.4.6 でエイリアスに差し替え。 |
| `www` | A | 上記と同じ静的 IP | `www.axiola.jp`。方法 B の場合はのちに 1.4.6 でエイリアスに差し替え。 |
| `origin` | A | 上記と同じ静的 IP | **方法 B のみ**。CloudFront のオリジン用（`origin.axiola.jp`）。**1.4.6 では変更しない。** |

手順:

1. **「レコードを作成」** をクリックし、**レコード名** 空欄・**タイプ** A・**値** に Lightsail の **静的 IP**・**TTL** `300` などで **「レコードを作成」**。
2. 同様に **レコード名** `www`・**タイプ** A・**値** 同じ静的 IP で **「レコードを作成」**。
3. **方法 B でオリジン用サブドメインを使う場合**のみ: **レコード名** `origin`・**タイプ** A・**値** 同じ静的 IP で **「レコードを作成」**。これで `origin.axiola.jp` が Lightsail の静的 IP を向く。

これで `http://example.com` および `http://www.example.com` で Lightsail の WordPress にアクセスできます。方法 B のときは、HTTPS 化の手順（1.4.5〜1.4.6）でルートと www を CloudFront 向けに差し替え、`origin` はそのまま Lightsail の静的 IP のままにします。

### 1.4 HTTPS（SSL）証明書の設定

独自ドメインで HTTPS を有効にする方法は次の **3 通り** です。

| 方法 | 説明 |
|------|------|
| **A. Lightsail の証明書＋ロードバランサー** | Lightsail で証明書を新規作成し、Lightsail ロードバランサーにアタッチする。 |
| **B. 既存の ACM 証明書＋CloudFront** | すでに **AWS Certificate Manager (ACM)** に証明書がある場合は、**CloudFront** を前面に置き、その証明書を使う。Lightsail のロードバランサーには ACM 証明書をアタッチできないため、CloudFront 経由にする。 |
| **C. インスタンス直で Let's Encrypt（bncert-tool）** | ロードバランサー・CloudFront を使わず、**Lightsail インスタンス上** で Bitnami の **bncert-tool** により **Let's Encrypt** の証明書を取得・自動更新する。**追加費用 $0**。Route 53 の A は **静的 IP のまま** でよい。 |

**費用的な目安**: **最も廉価なのは方法 C**（追加 $0）です。方法 A の Lightsail ロードバランサーは **月額約 $18** の固定費がかかります。方法 B は ACM 証明書が無料で、CloudFront は従量課金のため、月間アクセスが少ない場合は無料枠内〜数ドル程度に収まります。**Lightsail だけで完結させたい・追加コストをかけずに HTTPS にしたい** 場合は方法 C を検討してください。詳細は「[1.4 方法 A・B・C の比較](#14-方法-a・b・c-の比較)」を参照。

**既存の ACM 証明書を活用する場合は「1.4 の別案: 既存の ACM 証明書を使う場合（CloudFront）」** に進んでください。Lightsail の証明書から始める場合は、以下の 1.4.1 以降を実施します。

---

#### 方法 A: Lightsail の証明書＋ロードバランサー

##### 1.4.1 証明書の作成（Lightsail）

1. **Lightsail コンソール** を開き、リージョンが WordPress インスタンスと同じ（例: 東京）であることを確認する。
2. 左メニュー **「ネットワーク」** → **「証明書」**（Certificates）をクリック。
3. **「証明書の作成」**（Create certificate）をクリック。
4. **証明書名** を入力（例: `axiola-jp-cert`）。
5. **ドメイン名** に、対象ドメインを追加する。
   - ルートドメイン（例: `axiola.jp`）と `www`（例: `www.axiola.jp`）の両方を使う場合は、両方を追加する（1 証明書に複数ドメインを追加可能）。
6. **「証明書の作成」** をクリックする。

作成後、**「検証のための CNAME レコード」** が表示されます。この CNAME を **Route 53** のホストゾーンに追加するまで証明書は「保留中」のままです。

##### 1.4.2 証明書検証用 CNAME を Route 53 に追加

1. **Route 53**（infrastructure アカウント）→ **ホストゾーン** → 対象ドメイン（例: `axiola.jp`）を開く。
2. Lightsail の証明書画面に表示されている **CNAME 名** と **CNAME 値** を確認する。
3. **「レコードを作成」** をクリックし、次を設定する。
   - **レコード名**: Lightsail に表示されている CNAME 名（例: `_xxxxxxxx.axiola.jp` のような形式。ホストゾーン名が `axiola.jp` の場合は、レコード名は `_xxxxxxxx` の部分だけにする場合があります。Lightsail の表示どおりに従う）。
   - **レコードタイプ**: **CNAME**
   - **値**: Lightsail に表示されている CNAME 値（1 行で、末尾のピリオドあり・なしは Route 53 の入力ルールに従う）。
   - **TTL**: `300` など。
4. 証明書に複数ドメインを入れている場合、**検証用 CNAME が複数** あることがあります。その分だけ同様にレコードを作成する。
5. **「レコードを作成」** をクリックする。

数分〜最大 72 時間で、Lightsail の証明書の状態が **「検証済み」**（Valid）に変わります。

##### 1.4.3 ロードバランサーの作成と証明書のアタッチ

1. **Lightsail コンソール** → **「ネットワーク」** → **「ロードバランサー」** を開く。
2. **「ロードバランサーの作成」** をクリックする。
3. **名前** を入力（例: `company-hp-lb`）。**リージョン** は WordPress インスタンスと同じにする。
4. **「インスタンスの追加」** で、対象の WordPress インスタンスにチェックを入れ、**「作成」** をクリックする。
5. 作成したロードバランサーを開き、**「カスタムドメイン」**（Custom domains）タブをクリックする。
6. **「証明書のアタッチ」**（Attach certificate）をクリックし、**1.4.1** で作成した **検証済みの証明書** を選択して **「アタッチ」** をクリックする。
7. **「ドメインの割り当て」** で、使用するドメイン（例: `axiola.jp` と `www.axiola.jp`）を追加する。

ロードバランサーに **DNS 名**（例: `company-hp-lb.region.cs.amazonlightsail.com`）が表示されます。この名前をメモするか、次の手順で Route 53 を更新する際に使います。

##### 1.4.4 Route 53 をロードバランサー向けに更新

HTTPS でアクセスするには、ドメインの名前解決先を **インスタンスの静的 IP** から **ロードバランサー** に変更します。

1. **Route 53** → **ホストゾーン** → 対象ドメインを開く。
2. **1.3** で作成した **A レコード**（ルートドメインおよび `www`）を編集する。
   - **編集** をクリックし、**値** を Lightsail ロードバランサーの **DNS 名** に変更する。
   - Route 53 で **エイリアス** が利用できる場合: **「エイリアス」** をオンにし、トラフィックのルーティング先に **Lightsail のロードバランサー** を選択する（同じ AWS アカウントで Lightsail リソースを選べる場合）。エイリアスが選べない場合は、**CNAME レコード** でロードバランサーの DNS 名を指定する。
   - **ルートドメイン**（apex）では CNAME が使えないため、**エイリアス** を使うか、Lightsail の「カスタムドメイン」で案内されている方法（A レコード用の値など）に従う。
3. **「変更を保存」** をクリックする。

DNS の伝播後、`https://axiola.jp` および `https://www.axiola.jp` で HTTPS のサイトが表示されます。WordPress の **「設定」→「一般」** で **WordPress アドレス** と **サイトアドレス** を `https://` に変更しておくと、リダイレクトやリソースの URL が正しく動作します。

---

#### 方法 B: 既存の ACM 証明書を使う場合（CloudFront）

**AWS Certificate Manager (ACM)** にすでに証明書がある場合は、**CloudFront** を Lightsail の前に置き、その証明書を CloudFront にアタッチします。Lightsail のロードバランサーには ACM 証明書をアタッチできないため、CloudFront 経由にすることで既存証明書を活用できます。

**注意（リージョン）**: CloudFront に使える ACM 証明書は **バージニア北部 (us-east-1)** で発行されたものに限られます。証明書が他リージョン（例: 東京 ap-northeast-1）にある場合は、ACM で **us-east-1** に切り替え、同じドメインの証明書を新規リクエストするか、既に us-east-1 に証明書があることを確認してください。

**注意（ワイルドカード証明書）**: **ワイルドカード証明書（`*.axiola.jp`）だけではルートドメイン（`axiola.jp`）はカバーされません**。CloudFront の代替ドメイン名に `axiola.jp` と `www.axiola.jp` を追加している場合、証明書には **ルート（`axiola.jp`）とワイルドカード（`*.axiola.jp`）の両方** が含まれている必要があります。**ACM では既存の証明書にドメインを後から追加することはできません。** ルートを含めるには、**`axiola.jp` と `*.axiola.jp` の両方を含む新しい証明書** を us-east-1 でリクエストしてください。手順は下記「1.4.4a ACM でルート＋ワイルドカード用の証明書を用意する」を参照。

##### 1.4.4a ACM でルート＋ワイルドカード用の証明書を用意する（us-east-1）

ワイルドカード（`*.axiola.jp`）のみの証明書しかない場合、CloudFront 用に **ルートとワイルドカードの両方** を含む証明書を **新規リクエスト** します。**CloudFront が production アカウントにあるため、この証明書は production アカウントの us-east-1 で発行**します。

1. **production アカウント** で **AWS コンソール** にログインし、リージョンを **バージニア北部 (us-east-1)** に切り替える。
2. **Certificate Manager (ACM)** を開く。
3. **「証明書をリクエスト」** をクリックする。
4. **証明書のタイプ**: **パブリック証明書** のまま。**ドメイン名の追加** で次を **両方** 入力する。
   - **FQDN**: `axiola.jp`（ルートドメイン）
   - **FQDN**: `*.axiola.jp`（ワイルドカード）
5. **検証方法**: **DNS の検証** を選択し、**「リクエスト」** をクリックする。
6. 証明書の状態が **「検証保留中」** のあいだに、ACM に表示される **検証用 CNAME レコード** を **infrastructure アカウント** の **Route 53** ホストゾーン（axiola.jp）に追加する。Route 53 が infrastructure、ACM が production のため **「Route 53 でレコードを作成」は使えません**。**infrastructure アカウント** で Route 53 を開き、表示されている **名前** と **値** の CNAME を、ルート用・ワイルドカード用の分だけ **手動で** 作成する。
7. 数分〜最大 72 時間で証明書の状態が **「発行済み」** になる。その後、CloudFront の **カスタム SSL 証明書** でこの証明書を選択する。

既存のワイルドカード証明書は別サービス（ALB など）で使っている場合はそのまま利用可能です。CloudFront 用にこの 1 枚を追加で発行する形になります。

**同一の新証明書を tools と iq-test の CloudFront でも使う場合** は、発行後に **「付録 A. 共通証明書への切り替え — tools と iq-test の CloudFront」** の手順で、各 CloudFront の「カスタム SSL 証明書」をこの証明書に切り替えてください。

##### 1.4.5 CloudFront ディストリビューションの作成

**production アカウント** で CloudFront を作成します。

1. **production アカウント** で **AWS コンソール** にログインし、**CloudFront** を開く（リージョンに依存しないグローバルサービス）。
2. **「ディストリビューションを作成」** をクリックする。
3. **オリジン設定**（**1.3 で `origin.axiola.jp` の A レコードを作成済みであることを前提とします**）
   - **オリジンのタイプ (Origin type)**: **カスタムオリジン (Custom origin)** を選ぶ。S3 バケットではなく、自前のサーバ（Lightsail）をオリジンにするため。
   - **オリジンドメイン**: **`origin.axiola.jp`** を指定する（1.3 で作成した A レコードが Lightsail の静的 IP を向いている）。
   - **オリジンパス (Origin path)**: **空のまま**（未入力）。WordPress はオリジンのルート（`/`）で提供するため、サブディレクトリを指定しない。
   - **プロトコル**: **HTTP のみ**（Lightsail インスタンスは HTTP で受け付ける想定。HTTPS 終端は CloudFront で行う）。
   - **HTTP ポート**: 80。**HTTPS ポート**: 443（未使用でよい）。
   - **オリジンアクセス**: **パブリック** のまま。
4. **デフォルトキャッシュビヘイビア**
   - **ビューワープロトコルポリシー**: **Redirect HTTP to HTTPS** を選択。
   - **許可された HTTP メソッド**: **GET, HEAD, OPTIONS** でよい（フォーム送信など POST を使う場合は **GET, HEAD, OPTIONS, PUT, POST, PATCH, DELETE** を選択）。
   - **キャッシュキーとオリジンリクエスト**: デフォルトのまま、または必要に応じてカスタマイズ。
5. **設定**
   - **代替ドメイン名 (CNAME)** に、使用するドメインを追加（例: `axiola.jp` と `www.axiola.jp`）。1 行に 1 つ。
   - **カスタム SSL 証明書**: **ACM 証明書を選択** を選び、**us-east-1** の証明書一覧から対象の証明書を選択する。一覧にない場合は、ACM コンソールでリージョンを **us-east-1** に切り替え、該当ドメインの証明書を発行してから再度選択する。
   - **デフォルトルートオブジェクト**: `index.php` または空（WordPress は `index.php` でルートを処理するため、空でも可）。
6. **「ディストリビューションを作成」** をクリックする。

作成後、**ディストリビューションのドメイン名**（例: `d1234abcd.cloudfront.net`）が表示されます。**このドメイン名をメモ**し、次の 1.4.6 で Route 53 のエイリアス先に使います。数分で「有効」状態になります。

##### 1.4.6 Route 53 を CloudFront 向けに更新

**infrastructure アカウント** の Route 53 で、ルートと `www` の A レコードを **production アカウント** の CloudFront ディストリビューションに向けます。**変更するのはルートドメインと `www` の 2 本だけです。** `origin.axiola.jp`（レコード名 `origin`）の A レコードはそのままにします。

**重要 — 「An alias to a CloudFront distribution... are global and available only in US East (N. Virginia).」と出る場合**: Route 53 も CloudFront も**グローバルサービス**ですが、この種のエイリアスレコードをコンソールで作成・編集する際、**AWS コンソールのリージョン表示** が US East (N. Virginia) 以外になっているとエラーになることがあります。**先に** コンソール右上のリージョンで **「US East (N. Virginia)」** を選び、**そのあと** Route 53 → ホストゾーンを開いてレコードを編集し直してください。

**Route 53（infrastructure）と CloudFront（production）が別アカウントの場合**: エイリアス先のドロップダウンには **同一アカウントの CloudFront しか表示されません**。**CloudFront のドメイン名**（例: `d1234abcd.cloudfront.net`）を入力できる欄があれば、1.4.5 でメモしたドメイン名を直接入力する。

1. **infrastructure アカウント** で AWS コンソールにログインし、**コンソール右上のリージョン** を **US East (N. Virginia)** に切り替える（ここを先にやらないとエイリアスでエラーになる）。
2. **Route 53** → **ホストゾーン** → 対象ドメイン（例: axiola.jp）を開く。
3. **1.3** で作成した **A レコード** のうち、**レコード名が空欄（ルート）** と **`www`** の 2 件だけを編集する。
   - 各レコードの **「編集」** をクリック。
   - **「エイリアス」** をオンにする。
   - **トラフィックのルーティング先** で **「CloudFront ディストリビューションへのエイリアス」** を選択する。**同一アカウントの CloudFront が一覧に出る場合** は 1.4.5 で作成したディストリビューションを選ぶ。**別アカウントのため一覧にない場合** は、**CloudFront のドメイン名**（例: `d1234abcd.cloudfront.net`）を入力できる欄があればそこに入力する。
   - ルートドメイン・`www` の両方で、同じ CloudFront ディストリビューション（または同じドメイン名）を指定する。
4. **「変更を保存」** をクリックする。

**方法 B 実施後の Route 53 の状態（axiola.jp の例）:**

| レコード名 | タイプ | 値（ルーティング先） | 備考 |
|------------|--------|------------------------|------|
| （空欄） | A（エイリアス） | CloudFront ディストリビューション | `axiola.jp` |
| `www` | A（エイリアス） | 上記と同じ CloudFront | `www.axiola.jp` |
| `origin` | A | Lightsail の静的 IP | CloudFront のオリジン用。**変更しない。** |

DNS の伝播後、`https://axiola.jp` および `https://www.axiola.jp` で、ACM 証明書による HTTPS のサイトが表示されます。WordPress の **「設定」→「一般」** で **WordPress アドレス** と **サイトアドレス** を `https://` に変更しておくと、リダイレクトやリソースの URL が正しく動作します。

---

#### 方法 C: インスタンス直で Let's Encrypt（廉価）

**ロードバランサーも CloudFront も使わず**、Lightsail インスタンス上で **Let's Encrypt** の証明書を取得し、HTTPS を有効にします。**追加費用は $0** で、証明書は **bncert-tool** により約 80 日ごとに自動更新されます。Route 53 の A レコードは **静的 IP のまま**（1.3 の状態）でよく、変更不要です。

**前提**: **Certified by Bitnami** の WordPress インスタンスであること。ドメイン（例: `axiola.jp` と `www.axiola.jp`）の DNS が **すでに Lightsail の静的 IP** を向いていること（1.3 まで完了していること）。bncert は **ロードバランサーや CDN の前には対応していない** ため、**インスタンス直アクセス** の構成で利用します。また、bncert が使う **TLS-ALPN-01** 検証のため、**Lightsail の「ネットワーク」で HTTPS（443）を開放** しておく必要があります（未開放だと証明書取得に失敗します。トラブル時は「[bncert で Let's Encrypt が失敗する（tls-alpn-01）](#bncert-で-lets-encrypt-が失敗するtls-alpn-01deactivating-auth)」を参照）。

##### 1.4.C1 bncert-tool の確認・インストール（必要な場合）

1. **Lightsail コンソール** から該当 WordPress インスタンスの **SSH クイック接続**（ターミナルアイコン）を開く。
2. 次のコマンドを実行する。
   ```bash
   sudo /opt/bitnami/bncert-tool
   ```
3. **「command not found」** と出る場合は bncert が入っていない。以下の手順でインストールする。
   ```bash
   wget -O bncert-linux-x64.run https://downloads.bitnami.com/files/bncert/latest/bncert-linux-x64.run
   sudo mkdir -p /opt/bitnami/bncert
   sudo mv bncert-linux-x64.run /opt/bitnami/bncert/
   sudo chmod +x /opt/bitnami/bncert/bncert-linux-x64.run
   sudo ln -sf /opt/bitnami/bncert/bncert-linux-x64.run /opt/bitnami/bncert-tool
   ```
   その後、再度 `sudo /opt/bitnami/bncert-tool` を実行する。
4. **「Welcome to the Bitnami HTTPS configuration tool」** と表示されれば利用可能。

##### 1.4.C2 Let's Encrypt 証明書の取得と HTTPS 有効化

1. `sudo /opt/bitnami/bncert-tool` を実行する。
2. **Primary domain**（メインのドメイン）を入力する（例: `axiola.jp`）。
3. **Alternate domains**（別名ドメイン）をスペース区切りで入力する（例: `www.axiola.jp`）。不要なら Enter のみでスキップ可能。
4. ドメインがまだインスタンスの静的 IP を向いていないと警告される。その場合は **1.3** の A レコードを静的 IP に設定し、DNS 伝播を待ってから再度実行する。
5. リダイレクトの設定を聞かれたら、推奨は次のとおり。
   - **Enable HTTP to HTTPS redirection**: **Y**（HTTP を HTTPS にリダイレクト）
   - **Enable non-www to www redirection** または **Enable www to non-www redirection**: 運用方針に合わせて選択（例: ルートに統一するなら **www → non-www** を有効）。
6. 変更内容の確認で **Y** を入力する。
7. **Let's Encrypt 用のメールアドレス** を入力する（証明書の有効期限通知などに使われる）。
8. Let's Encrypt の利用規約に **Y** で同意する。
9. 処理が完了すると、証明書が発行され、Web サーバーが HTTPS 対応・リダイレクト設定される。**証明書は約 80 日ごとに bncert により自動更新** されます。

##### 1.4.C3 WordPress の URL を https に変更

1. ブラウザで **https://axiola.jp/wp-admin** にアクセスし、WordPress 管理画面にログインする。
2. **設定** → **一般** を開く。
3. **WordPress アドレス (URL)** と **サイトアドレス (URL)** を **`https://axiola.jp`**（または www に統一している場合は `https://www.axiola.jp`）に変更し、**変更を保存** する。

**方法 B（CloudFront）から方法 C に切り替えた場合**: `wp-config.php` に **`WP_HOME`** / **`WP_SITEURL`** の define を追加している場合は、**方法 C では不要**なので削除してかまいません。define を削除すると管理画面の「WordPress アドレス」「サイトアドレス」が編集可能になり、上記のとおり管理画面から https の URL を設定すれば十分です。

これで **方法 C** の設定は完了です。Route 53 の A レコードは **静的 IP のまま** で、LB や CloudFront は不要です。

**参考**: [Lightsail で WordPress を HTTPS 化（公式）](https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-enabling-https-on-wordpress.html)、[bncert による Let's Encrypt 証明書の自動更新](https://repost.aws/knowledge-center/lightsail-bitnami-renew-ssl-certificate)。

---

### 1.5 動作確認

- **HTTP（1.3 まで実施後）**: ブラウザで `http://<あなたのドメイン>/` を開き、WordPress のサイトが表示されることを確認。ネームサーバー変更直後は反映に時間がかかることがあるため、表示されない場合はしばらく待ってから再試行する。
- **HTTPS（1.4 まで実施後）**: `https://<あなたのドメイン>/` を開き、証明書が有効（南京錠マーク）でサイトが表示されることを確認する。

#### 1.4 方法 A・B・C の比較

| 項目 | 方法 A（Lightsail LB） | 方法 B（ACM + CloudFront） | 方法 C（Let's Encrypt 直） |
|------|-------------------------|----------------------------|-----------------------------|
| **証明書** | 無料（Lightsail 管理） | 無料（ACM） | 無料（Let's Encrypt、bncert で自動更新） |
| **HTTPS 用の追加コスト** | **Lightsail LB: 月額約 $18**（固定） | **CloudFront: 従量課金** | **追加 $0**（インスタンス＋静的 IP のみ） |
| **低トラフィック時** | 月 $18 かかる | 無料枠内なら実質 $0〜数ドル | **$0** |
| **高トラフィック時** | 月 $18 で一定 | 転送量・リクエストに応じて増加 | インスタンス料金のみ（転送は Lightsail の枠内） |
| **向いているケース** | 月額固定・トラフィック多め | 既存 ACM を流用・CDN も使いたい | **費用を抑えたい・Lightsail だけで完結させたい** |

**廉価・シンプルさの目安**

| 観点 | 方法 A（Lightsail LB） | 方法 B（CloudFront） | 方法 C（Let's Encrypt） |
|------|-------------------------|------------------------|--------------------------|
| **構成** | Route 53 → LB → インスタンス | Route 53 → CloudFront → オリジン → インスタンス（wp-config・ServerAlias 等の調整あり） | **Route 53 → 静的 IP → インスタンス**（LB・CloudFront なし） |
| **WordPress** | 管理画面で URL を https に変更すればよい | wp-config の X-Forwarded-Proto と固定 URL が必須、場合により ServerAlias | 管理画面で URL を https に変更。bncert 実行のみ。 |
| **保守** | 証明書は Lightsail が更新。LB の運用のみ。 | CloudFront・Route 53・wp-config・Web サーバー設定と確認箇所が分散 | **証明書は bncert が約 80 日ごとに自動更新。** Route 53 は静的 IP のまま触らない。 |

**結論**: **追加費用をかけずに HTTPS にしたい** 場合は **方法 C（インスタンス直で Let's Encrypt）** が最安です。Lightsail の WordPress に同梱される bncert-tool で証明書を取得し、**Route 53 は 1.3 のまま（A を静的 IP に向ける）** で利用できます。**運用を AWS まかせにしたい・LB で止めておきたい** 場合は方法 A（月額約 $18）、**既存の ACM 証明書や CDN を活かしたい** 場合は方法 B を選んでください。

---

## 2. Amazon SES の設定

### 2.0 SES が infrastructure アカウントにある場合（production の Lightsail から利用する）

**SES はすでに infrastructure アカウントでセットアップ済み**（ドメイン認証・DKIM・SPF・SMTP 認証情報のいずれかが済んでいる）場合は、**production アカウントで SES を新規設定する必要はありません**。production の Lightsail 上で動く WordPress から、**infrastructure アカウントの SES** をそのまま使えます。

**やること**

1. **infrastructure アカウント** にログインし、**SES** を開く（SES を有効にしている **リージョン** を確認しておく。例: 東京 `ap-northeast-1`）。
2. **送信に使うドメイン**（例: `axiola.jp`）が **Identities** で認証済みであること、**DKIM** 用 CNAME が Route 53（infrastructure のホストゾーン）に登録済みであることを確認する。未設定なら 2.2 のとおり infrastructure 側でドメイン認証と DKIM を完了する。
3. **SMTP 認証情報** を用意する。  
   - **既に infrastructure で SMTP 用の IAM 認証情報を作成済み** なら、その **SMTP ユーザー名** と **SMTP パスワード** を控える。  
   - まだ無い場合は、**infrastructure アカウント** の SES → **「SMTP の設定」**（SMTP settings）→ **「Create SMTP credentials」** で新規作成し、表示された **SMTP ユーザー名** と **SMTP パスワード** を安全に保存する。
4. **production の WordPress**（Lightsail）の **WP Mail SMTP** で、次を設定する。
   - **SMTP ホスト**: infrastructure の SES があるリージョンのエンドポイント（例: 東京なら `email-smtp.ap-northeast-1.amazonaws.com`）。
   - **SMTP ポート**: **587**、暗号化: **TLS**、認証: **オン**。
   - **SMTP ユーザー名** / **SMTP パスワード**: 上記で用意した **infrastructure アカウント** の SMTP 認証情報。
   - **送信元メールアドレス**: infrastructure の SES で認証済みのドメインのアドレス（例: `wordpress@axiola.jp`）。

これで、production の Lightsail（WordPress）から infrastructure の SES 経由でメール送信されます。**セクション 3（SPF）** は Route 53 が infrastructure にあれば、既に SPF 用 TXT を入れている可能性が高いので、未設定の場合のみ 3 の手順を実施してください。**2.1〜2.4 は、SES を infrastructure で使う場合はスキップ** して、**4. WordPress 側** の WP Mail SMTP 設定（4.4）に進んでください。

---

### 2.1 SES を有効化するリージョンの確認

- **Lightsail のリージョン**（例: 東京 `ap-northeast-1`）と **同じリージョン** で SES を利用することを推奨します。
- AWS コンソール右上でリージョンを **東京 (ap-northeast-1)** などに切り替えてから、**「Amazon Simple Email Service」** を開きます。

### 2.2 送信元の認証（ドメインの認証）

1. **SES コンソール** → 左メニュー **「設定」** → **「Identities」**（アイデンティティ）を開く。
2. **「Create identity」**（アイデンティティの作成）をクリック。
3. **Identity type**: **Domain** を選択。
4. **Domain**: 使用するドメイン（例: `example.com`）を入力。
5. **Advanced DKIM settings** はデフォルト（DKIM 署名有効）のままで問題ありません。
6. **「Create identity」** をクリック。

作成後、**DKIM 用の CNAME レコード** が 3 つ表示されます。**Route 53 で同じ AWS アカウントのホストゾーンを使っている場合**は、「Add record to Route 53」でそのまま Route 53 にレコードを追加できます。追加後、SES 側で「Verified」になるまで数分〜最大 72 時間かかることがあります。

**Route 53 に手動で追加する場合**

- **Identities** で該当ドメインをクリック → **DKIM** タブに、**Name** と **Value** の CNAME が 3 組表示されます。
- Route 53 のホストゾーンで **「レコードを作成」** を 3 回行い、それぞれ:
  - **レコードタイプ**: CNAME
  - **レコード名**: SES に表示されている **Name** の値（例: `xxxx._domainkey.example.com` → レコード名は `xxxx._domainkey` のみでよい場合が多い。コンソールの表記に従う）
  - **値**: 対応する **Value**（CNAME の値）をそのまま入力

### 2.3 SMTP 認証情報の作成

WordPress の WP Mail SMTP から SES の SMTP エンドポイントに接続するために、**IAM で SMTP 用の認証情報** を発行します。

1. **SES コンソール** → 左メニュー **「SMTP の設定」**（または **「Account dashboard」** 内の SMTP settings）を開く。
2. **「Create SMTP credentials」**（SMTP 認証情報の作成）をクリック。
3. **IAM ユーザー名** を入力（例: `ses-smtp-wordpress`）→ **「Create user」** をクリック。
4. **SMTP ユーザー名** と **SMTP パスワード** が表示されます。**パスワードはこの画面でしか表示されない**ため、必ず安全な場所に保存してください。  
   - この **ユーザー名** と **パスワード** を、後述の WP Mail SMTP の「SMTP ユーザー名」「SMTP パスワード」に **そのまま**（Base64 エンコードなどは不要）入力します。

**SES SMTP エンドポイント**（リージョンごと）の例:

- 東京 (ap-northeast-1): `email-smtp.ap-northeast-1.amazonaws.com`
- ポート: **587**（TLS）、または 465（SSL）。Lightsail では 587 を推奨。

### 2.4 サンドボックスと本番リクエスト

- **サンドボックス**の間は、SES に「認証済みのメールアドレス」にしか送信できません。**Identities** で **「Verify a new email」** から、受信したいメールアドレス（例: 会社の `info@example.com`）を追加して認証します。
- **本番**で不特定のアドレスに送信するには、下記のとおり **本番アクセス（サンドボックス解除）** を申請します。

#### サンドボックス解除の申請手順（Request production access）

**SES を利用しているアカウント**（infrastructure アカウントで SES を設定している場合はそのアカウント）で実施します。

1. **AWS コンソール** にログインし、**SES を有効にしているリージョン**（例: 東京 ap-northeast-1）に切り替える。
2. **Amazon Simple Email Service** を開く。
3. 左メニューで **「Account dashboard」**（アカウントダッシュボード）をクリックする。
4. **「Request production access」**（本番アクセスをリクエスト）ボタンをクリックする。
5. 表示されるフォームに必要事項を入力する。目安:
   - **Mail type**: **Transactional**（トランザクション）を選ぶ（お問い合わせフォームなど、ユーザー操作に応じた送信用）。
   - **Website URL**: 送信元となるサイトの URL（例: `https://axiola.jp`）。
   - **Use case description**（ユースケースの説明）: 英語で、**会社のウェブサイトのお問い合わせフォームから顧客の問い合わせを受け取り、指定したメールアドレスに転送する** といった内容を簡潔に書く。例:  
     `We use SES to send emails from our company website contact form (https://axiola.jp/contact/). Recipients are our staff who respond to customer inquiries. We do not send marketing or bulk emails.`
   - **Compliance**: スパムやバウンスの対応、オプトインの有無など、案内に従って回答する。トランザクションのみの場合は「We only send transaction emails (contact form submissions) to addresses that have submitted the form.」などでよい。
6. **「Submit request」**（リクエストを送信）をクリックする。

審査は通常 **24 時間以内** に完了することが多く、メールで結果が届きます。承認されると **Account dashboard** のステータスが **Production access** に変わり、認証済みでないアドレスにも送信できるようになります。否認された場合はメールに理由が記載されるため、内容に合わせて申請内容を修正して再申請できます。

#### 本番申請で「追加情報」「ID の検証」を求められた場合

AWS から **「使用計画について詳しい情報をご提供ください」** や **「送信に使用する ID を検証する必要があります」** といった返信が届いた場合の対応です。

**1. ID（ドメイン）の検証を確認する**

- **SES** → **Identities** で、送信元に使っている **ドメイン**（例: `axiola.jp`）のステータスが **Verified** になっているか確認する。未検証の場合は、表示される **DKIM 用 CNAME** を Route 53 に追加し、検証完了まで待つ。
- 送信元アドレス（例: `no-reply@axiola.jp`）は **ドメイン認証** 済みであれば、個別のメールアドレス検証は不要です。ドメインが Verified であれば「We use the verified domain axiola.jp for the From address.」と返信に書けます。
- **同一ドメイン（axiola.jp）内の複数サービス**（お問い合わせフォーム、ツールの通知メールなど）で **SES を共用して問題ありません**。検証はドメイン単位のため、axiola.jp で検証済みであれば、そのドメインのどのアドレスからでも送信できます。

**2. 追加情報の返信（日本語の例文）**

AWS の案内では、**送信頻度・受信者リストの管理・バウンス・申し立て・解除申請の扱い・メールサンプル** を求められます。お問い合わせフォームのみの利用なら、以下のような日本語の返信を **そのメールに返信** して送ります（必要に応じて会社名・URL を置き換えてください）。

```
お世話になっております。

SES の使用計画について、以下のとおり詳細を記載いたします。

■ メールの種類・用途
axiola.jp ドメインで運用している複数のサービス（コーポレートサイトのお問い合わせフォーム https://axiola.jp/contact/ のほか、同一ドメイン配下の各種トランザクションメール）で、SES を共通で利用しております。送信先は (1) 当社担当者用の固定アドレス（フォーム内容の通知）および (2) フォーム送信者が自身で入力したメールアドレス（確認メール・自動返信を送る場合）です。いずれもフォーム送信やユーザー操作に伴う 1 通ずつのトランザクションであり、マーケティングメール、ニュースレター、一斉送信は行っておりません。

■ 送信頻度
月あたり数件〜数十件程度のフォーム送信を見込んでおり、送信はユーザーが明示的にフォームを送信したときのみ発生します。

■ 受信者リストの管理
事前に保有する「受信者リスト」はありません。送信先は (1) フォーム設定で指定した固定の担当者用アドレス と (2) 各フォーム送信のたびにユーザーが入力したメールアドレス（確認メール用）のみです。外部から取得したリストや購入リストへの送信は行いません。

■ バウンス・苦情・解除申請の管理
SES の送信統計およびバウンス・苦情の通知を確認し、バウンスや苦情が発生した場合は原因を調査して対応します。ユーザーが入力したアドレスへの確認メールがバウンスした場合も、その都度の送信に対するものであり、事前のリストを更新する運用はありません。いずれもトランザクションメールのみのため、マーケティング用のオプトアウトリストはなく、受信者はメーリングリストに登録しているわけではありません。

■ 送信に使用する ID の検証について
送信元ドメイン（axiola.jp）は SES の Identities で検証済みです。送信元アドレスとしては no-reply@axiola.jp などを使用しており、当該ドメインの DKIM および SPF は Route 53 で設定済みです。

■ 送信予定のメールのサンプル
お問い合わせフォーム通知メールの一例です。簡潔な事務連絡であり、プロモーションや HTML 中心のマーケティングコンテンツは送っておりません。

  件名: [お問い合わせ] axiola.jp からの問い合わせ
  From: Axiola <no-reply@axiola.jp>
  To: info@axiola.jp（当社担当者）

  本文（例）:
  axiola.jp のお問い合わせフォームからメッセージが届いています。

  お名前: [送信者名]
  メールアドレス: [送信者メール]
  お問い合わせ内容: [送信者のメッセージ]

  --
  このメールは https://axiola.jp のお問い合わせフォームから送信されました。

以上です。追加でご不明点がございましたらお知らせください。
```

**英語で返信する場合**（以下は英語の例文。AWS から英語で案内が来ている場合は英語返信でも可）:

```
Thank you for your message. Please find below the additional information regarding our SES usage.

• Mail type and purpose:
  We use Amazon SES only for transactional emails from our company website contact form (https://axiola.jp/contact/) and other services under the axiola.jp domain. Recipients are (1) our designated staff address (e.g. info@axiola.jp) for form notifications, and (2) the email address that the user entered in the form when we send a confirmation/auto-reply to the submitter. Each send is triggered by a single form submission or user action. We do not send marketing, newsletters, or bulk emails.

• Sending frequency:
  We expect a low volume: typically a few to a few dozen contact form submissions per month. Sending occurs only when a user explicitly submits the form.

• Recipient list:
  We do not maintain a separate "recipient list." Recipients are (1) our fixed business email address configured in the form, and (2) the email address that the user types in each form submission (for confirmation emails). We do not send to pre-collected or purchased lists.

• Bounces, complaints, and opt-out:
  We will monitor SES sending statistics and bounce/complaint notifications and take action as needed. If a confirmation email to a user-entered address bounces, it is a one-off event; we do not maintain a list to update. We only send transactional emails, so there is no marketing opt-out list; recipients are not subscribing to a mailing list.

• Identity verification:
  The sending domain (axiola.jp) is verified in SES Identities, and we use addresses such as no-reply@axiola.jp as the From address. DKIM and SPF are configured in our DNS (Route 53) for this domain.

• Sample of the email we send:
  Below is a typical example of the contact form notification email (subject and body). The content is plain and professional; we do not send promotional or HTML-heavy marketing content.

  Subject: [Contact Form] Inquiry from axiola.jp
  From: Axiola <no-reply@axiola.jp>
  To: info@axiola.jp (our staff)

  Body (example):
  You have received a message from the contact form on axiola.jp.

  Name: [Sender's name]
  Email: [Sender's email]
  Message: [Sender's message]

  -- 
  This email was sent via the contact form on https://axiola.jp

If you need any further details, please let us know.
```

**3. 返信後の流れ**

- 上記を AWS からのメールに **返信** で送る。同じスレッドで返信すると申請と紐づきます。
- 案内どおり **24 時間以内** に審査結果の連絡が来ることが多いです。承認されるとサンドボックスが解除されます。

---

## 3. Route 53 に SPF（と必要に応じて DMARC）を追加（メール到達率のため）

SES から送信するドメインの **SPF** を設定すると、届いたメールが迷惑メールになりにくくなります。Route 53 のホストゾーンに **TXT レコード** を 1 本追加します。

**重要**: 送信元が `wordpress@axiola.jp` や `no-reply@axiola.jp` のように **ルートドメイン（axiola.jp）** のアドレスの場合、SPF は **ルートドメイン** に設定する必要があります。**bounce.axiola.jp** などサブドメインだけに SPF がある場合（SES のバウンス用など）は、**ルート（axiola.jp）用の SPF レコード** を別途追加してください。レコード名を **空欄** にするとルートドメインになります。

1. Route 53 の **「ホストゾーン」** → 対象ドメインのゾーンを開く。
2. **「レコードを作成」**。
3. 次のように設定:
   - **レコード名**: **空欄**（ルートドメイン `axiola.jp` / `example.com` 用）。空にすると `axiola.jp` 自体の SPF になる。
   - **レコードタイプ**: **TXT**。
   - **値**:  
     `"v=spf1 include:amazonses.com ~all"`  
     （ダブルクォートを含めて 1 本のレコードとして入力。**SPF は 1 ドメインあたり 1 本の TXT だけ**が有効。既にルート用の SPF TXT がある場合は、新規追加せずそのレコードを編集し、`include:amazonses.com` を既存の `v=spf1 ...` に追加して 1 本にまとめる。TXT レコード自体は SPF 以外に DMARC や検証用など複数持てるが、SPF 用は 1 本にすること。）
   - **TTL**: `300` など。
4. **「レコードを作成」** をクリック。

**DMARC**（オプション）: 受信側にポリシーを伝えたい場合は、`_dmarc.example.com` のようなサブドメインに DMARC 用の TXT レコードを追加します。詳細は [AWS SES DMARC のドキュメント](https://docs.aws.amazon.com/ja_jp/ses/latest/dg/send-email-authentication-dmarc.html) を参照してください。

---

## 4. WordPress 側: Contact Form 7 と WP Mail SMTP（本番）

### 4.1 プラグインのインストール・有効化

1. WordPress 管理画面（`http://<ドメインまたは静的IP>/wp-admin`）にログイン。
2. **プラグイン** → **新規追加** で次をインストールし、**有効化** する。
   - **Contact Form 7**
   - **WP Mail SMTP** または **FluentSMTP** など、**「その他の SMTP」**（汎用 SMTP）でホスト・ポート・認証を設定できるプラグイン。  
     （WP Mail SMTP の「Amazon SES」は Pro 有料のため、無料で使う場合は **「その他の SMTP」** を選び、SES の SMTP エンドポイントと認証情報を入力する。4.4 参照。）

### 4.2 Contact Form 7 の基本設定

1. **Contact Form 7** → **連絡先** で、デフォルトの「お問い合わせ」フォームの **編集** を開く。
2. **「フォーム」** タブで必要に応じて項目を編集。
3. **「メール」** タブで以下を設定:
   - **宛先**: 問い合わせを受け取りたいメールアドレス（例: `info@example.com`）。SES サンドボックス中は **SES で認証済みのアドレス** にすること。
   - **送信元**: 例: `WordPress <wordpress@example.com>` または `[your-name] <wordpress@example.com>`。`example.com` は SES で認証したドメインに合わせる。
   - **件名・本文**: タグ（`[your-name]` など）を使って任意で設定。
4. **保存**。

### 4.3 お問い合わせページにフォームを表示

1. **固定ページ** で、スラッグが `contact` の「お問い合わせ」ページを編集。
2. 本文に Contact Form 7 の **ショートコード** を貼り付け（例: `[contact-form-7 id="123" title="お問い合わせ"]`）。**Contact Form 7** → **連絡先** で表示されているショートコードをコピーして使用。
3. **更新** して保存。

### 4.4 WP Mail SMTP（または SMTP プラグイン）で SES を指定

**WP Mail SMTP の「Amazon SES」は Pro プランが必要** な場合があります。**無料で SES を使う** には、メール送信方法で **「その他の SMTP」**（Other SMTP）を選び、SES の **SMTP エンドポイントと認証情報** を手動で入力します。SES は標準で SMTP インターフェースを提供しているため、**「その他の SMTP」で同じように送信** できます。代替として **FluentSMTP** など、無料で「SMTP」を選べるプラグインでも同様の設定が可能です。

1. **WP Mail SMTP**（または利用する SMTP プラグイン）→ **設定**（または **Settings**）を開く。
2. **メール送信方法**（または **Mailer**）で **「その他の SMTP」**（Other SMTP）を選択する。  
   （「Amazon SES」が無料で選べる場合はそれでも可。Pro が必要な場合は「その他の SMTP」で以下を入力する。）
3. **SMTP 設定** に次を入力する:
   - **SMTP ホスト**: `email-smtp.ap-northeast-1.amazonaws.com`（SES を有効にしているリージョンに合わせて変更。東京なら上記。**infrastructure の SES を使う場合も同じリージョンのエンドポイント**を指定する。）
   - **SMTP ポート**: **587**
   - **暗号化**: **TLS** または **STARTTLS**
   - **認証**: **オン**
   - **SMTP ユーザー名**: 「2.3 SMTP 認証情報の作成」で取得した **SMTP ユーザー名**（**SES が infrastructure にある場合は、infrastructure アカウントで作成した SMTP 認証情報** のユーザー名）
   - **SMTP パスワード**: 同上の **SMTP パスワード**（SES の「Create SMTP credentials」で表示されたパスワードを **そのまま** 入力する。Base64 エンコードは不要。）
4. **送信元メールアドレス** を、SES で認証したドメインのアドレス（例: `wordpress@example.com`。**infrastructure の SES を使う場合は、その SES で認証済みのドメインのアドレス**）に設定。
5. **「設定を保存」** をクリック。

### 4.5 テスト送信

1. WP Mail SMTP の **「テストメールを送信」**（または **Email Test**）で、テストメールを送信。
2. エラーが出ないこと、指定した宛先にメールが届くことを確認。
3. その後、実際のお問い合わせページ（`/contact/`）からフォーム送信し、同様に届くことを確認。

---

## 5. チェックリスト（本番運用前）

| 項目 | 確認 |
|------|------|
| Route 53 のホストゾーンを作成し、レジストラの NS を切り替えた | ☐ |
| A レコードでドメイン（と www）を Lightsail の静的 IP に向けた | ☐ |
| （HTTPS 利用時・方法 A）Lightsail で証明書を作成し、検証用 CNAME を Route 53 に追加した | ☐ |
| （HTTPS 利用時・方法 A）ロードバランサーを作成し、証明書をアタッチし、Route 53 を LB 向けに更新した | ☐ |
| （方法 B）1.3 でオリジン用サブドメイン（例: `origin.axiola.jp`）の A レコードを Route 53 に追加した | ☐ |
| （HTTPS 利用時・方法 B）CloudFront を作成し、既存の ACM 証明書（us-east-1）をアタッチし、Route 53 のルート・www を CloudFront 向けに更新した（`origin` は静的 IP のまま） | ☐ |
| （HTTPS 利用時・方法 C）Lightsail に SSH でログインし、bncert-tool で Let's Encrypt 証明書を取得・HTTPS 有効化した。WordPress の「設定」→「一般」で URL を https に変更した（Route 53 は静的 IP のまま） | ☐ |
| （SES を production で新規設定する場合）SES でドメインを認証し、DKIM の CNAME を Route 53 に追加した | ☐ |
| （SES が infrastructure にある場合）infrastructure の SES SMTP 認証情報を取得し、production の WP Mail SMTP にその認証情報と同一リージョンの SMTP エンドポイントを設定した | ☐ |
| SES でドメインを認証し、DKIM の CNAME を Route 53 に追加した（infrastructure で済んでいればスキップ可） | ☐ |
| Route 53 に SPF 用 TXT レコード（`v=spf1 include:amazonses.com ~all`）を追加した | ☐ |
| SES の SMTP 認証情報を作成し、WP Mail SMTP に設定した | ☐ |
| サンドボックス利用時は、受信先メールを SES で認証した | ☐ |
| Contact Form 7 のメール宛先・送信元を本番用に設定した | ☐ |
| テスト送信と実際のフォーム送信の両方でメールが届くことを確認した | ☐ |

---

## 6. トラブルシューティング

### メールが届かない

- **SES サンドボックス**: 送信先が「認証済みのメールアドレス」か確認。本番解除を申請する。**「Email address is not verified」で拒否される場合** は下記を参照。
- **SPF/DKIM**: Route 53 の DKIM（CNAME）と SPF（TXT）が正しく設定されているか、DNS の伝播を待ってから再度送信テストする。
- **WP Mail SMTP**: 設定画面で「テストメール」を送り、エラーメッセージの有無を確認。ポート 587・TLS、認証オンを再確認。

### 「Email address is not verified」「Message rejected」で SES が拒否する

**エラー例**: `Message rejected: Email address is not verified. The following identities failed the check in region AP-NORTHEAST-1: user@example.com`

**原因**: SES が **サンドボックス** のときは、**送信先（To）のメールアドレス** も **SES で認証済み** である必要があります。送信元（From）が認証済みでも、**宛先が未認証** だと DATA 送信時に 554 で拒否されます。テスト送信で `user@example.com` などの未認証アドレスを指定しているとこのエラーになります。

**対処**

1. **infrastructure アカウント**（SES を設定しているアカウント）で **SES** を開く。
2. **Identities** → **「Verify a new email address」**（新しいメールアドレスを認証）をクリック。
3. **テスト送信の宛先** に使うメールアドレス（例: 実際に受け取れる `info@axiola.jp` や個人アドレス）を入力し、認証メールを送信してリンクをクリックする。
4. 認証完了後、**WP Mail SMTP の「テストメールを送信」** で **宛先** をその認証済みアドレスに変更して再送する。Contact Form 7 の **メールの宛先** も、サンドボックス中は認証済みアドレスにすること。

本番で不特定のアドレスに送りたい場合は、SES の **「Account dashboard」** から **「Request production access」** でサンドボックス解除を申請する。

### テスト送信は成功するが、Contact Form 7 の「送信」だけ失敗する（メッセージの送信に失敗しました）

**症状**: WP Mail SMTP の「テストメールを送信」は成功するが、**https://axiola.jp/contact/** でフォームの「送信」をクリックすると **「メッセージの送信に失敗しました。後でまたお試しください。」** と表示される。

**主な原因と確認**

1. **Contact Form 7 の「メール」タブの宛先が SES で未認証**
   - **Contact Form 7** → **連絡先** → 該当フォームの **編集** → **「メール」** タブを開く。
   - **宛先**（To）に指定しているアドレスが、**SES サンドボックス** の場合は **SES で認証済み** である必要があります。未認証のアドレスだと送信時に SES が拒否し、CF7 は「送信に失敗しました」と表示します。**宛先** を、WP Mail SMTP のテスト送信で届いたのと同じ **認証済みアドレス** に変更して保存し、再度フォームから送信してみる。

2. **送信元（From）が認証済みドメインか**
   - **「メール」** タブの **送信元**（From）に、SES で認証済みのドメインのアドレス（例: `WordPress <no-reply@axiola.jp>`）が入っているか確認する。未認証のドメインやアドレスだと SES が拒否することがある。

3. **WordPress のデバッグログでエラー内容を確認**
   - 原因が分からない場合は、Lightsail 上で `wp-config.php` に `define('WP_DEBUG', true);` と `define('WP_DEBUG_LOG', true);` を追加し、フォーム送信後に **`/opt/bitnami/wordpress/wp-content/debug.log`** を確認する。SES の 554 エラーや PHP のエラーが出ていれば、宛先の認証や送信元の設定を再度見直す。

4. **メール本文のタグとフォームの項目名が一致しているか**
   - 「メール」タブの **本文** で使っているタグ（例: `[your-name]`、`[your-email]`）が、**「フォーム」** タブの入力項目の名前と一致しているか確認する。存在しないタグだけでも送信が止まる場合がある。

まず **1**（宛先を認証済みアドレスに）を確認すると解決することが多いです。

### サイトにアクセスできない（ドメインで）

- レジストラのネームサーバーが Route 53 の NS に切り替わっているか確認。
- Route 53 の A レコードの「値」が Lightsail の **静的 IP**（HTTP のみ・**方法 C（Let's Encrypt）** の場合）または **ロードバランサー**（**方法 A** の HTTPS 利用時）または **CloudFront**（**方法 B** の場合）になっているか確認。
- 反映に最大 48〜72 時間かかることがあるため、時間をおいて再確認する。

### CloudFront 403 ERROR（We can't connect to the server）

**「403 ERROR」「Bad request. We can't connect to the server for this app or website」** と表示される場合、CloudFront が **オリジン（Lightsail）に接続できていない** か、オリジンが **403 を返している** 可能性があります。

**確認・対処（順に試す）**

1. **オリジン（Lightsail）に直接アクセスできるか**
   - ブラウザまたは `curl` で **`http://origin.axiola.jp`** または **`http://<Lightsail の静的 IP>`** を開く。WordPress のページが表示されればオリジンは動いている。接続できない場合は Lightsail インスタンスが起動しているか、Web サーバーが動いているか確認する。

2. **Route 53 で origin.axiola.jp が正しく向いているか**
   - **origin**（または `origin.axiola.jp`）の **A レコード** が Lightsail の **静的 IP** を指しているか確認する。間違っていると CloudFront が別の先に接続しに行って 403 になることがある。

3. **Lightsail のネットワーク（ファイアウォール）**
   - Lightsail の **「ネットワーク」** タブで、該当インスタンスの **ファイアウォール** を確認する。**HTTP（80）** が **0.0.0.0/0**（すべての IPv4）から許可されているか。CloudFront のエッジからオリジンへはインターネット経由で接続するため、80 番が開いていないと接続できない。

4. **オリジン（Lightsail）の Web サーバーで axiola.jp を許可する**
   - CloudFront は **Host ヘッダーをカスタムで上書きできません**（「HeaderName : Host is not allowed」となる）。そのため、CloudFront からは **Host: axiola.jp**（ビューアーがアクセスしたドメイン）のままオリジンに届く。オリジン側の Web サーバーが **origin.axiola.jp 以外の Host を拒否している** と 403 になる。**Lightsail に SSH でログイン** し、Web サーバー設定で **axiola.jp** を許可する。
   - **Bitnami Apache** の例: バーチャルホストの設定を編集する（例: `/opt/bitnami/apache/conf/vhosts/wordpress-vhost.conf` または `httpd-app.conf`）。`ServerName` が `origin.axiola.jp` のブロックに **`ServerAlias axiola.jp`** を 1 行追加して保存し、`sudo /opt/bitnami/ctlscript.sh restart apache` で Apache を再起動する。**Bitnami Nginx** の場合は、該当 `server_name` に `axiola.jp` を追加する（例: `server_name origin.axiola.jp axiola.jp;`）。設定ファイルのパスは `ls /opt/bitnami/nginx/conf/` などで確認する。

5. **CloudFront のキャッシュ無効化**
   - 設定変更後、**無効化** タブでオブジェクトパス **`/*`** の無効化を作成し、数分後に再度アクセスして確認する。

### CloudFront ディストリビューションを削除できない

**方法 B から方法 A や方法 C に切り替える** など、company-hp 用の CloudFront を削除したい場合、CloudFront は **いったん「無効」にしないと削除できません**。

1. **ディストリビューションを無効化する**
   - **CloudFront** コンソール（production アカウント）を開く。
   - 削除したいディストリビューションの **ID** をクリックして詳細を表示する。
   - **「一般」** タブの **「編集」** をクリックする。
   - **「有効」**（Enabled）を **「無効」**（Disabled）に変更し、**「変更を保存」** する。
2. **状態の変化を待つ**
   - **状態**（State）が **「デプロイ済み」**（Deployed）から **「無効」**（Disabled）に変わるまで待つ。**通常 15〜20 分** かかることがあり、長いときは 30 分以上かかる場合もあります。画面を再読み込みして状態を確認する。
3. **削除する**
   - 状態が **「無効」** になったら、ディストリビューションを選択し、**「削除」**（Delete）をクリックする。確認画面で削除を実行する。

**「You can't delete this distribution while it's subscribed to a pricing plan」と出る場合**: そのディストリビューションが **CloudFront の料金プラン**（例: CloudFront Security Savings Bundle など）に加入しています。削除するには **先に料金プランを解約** する必要があります。**CloudFront** コンソールの左メニューで **「料金プラン」**（Pricing）または **「サブスクリプション」** を開き、該当するプランを **解約**（Cancel）する。解約後、**当月の請求サイクル終了後** にディストリビューションの削除が可能になります。それまではディストリビューションを **無効**（Disabled）のままにしておけば、トラフィックは発生せず、Route 53 を Lightsail の静的 IP に切り替えておけばサイトは方法 C のまま利用できます。請求サイクル終了後にあらためて削除を実行してください。

**注意**: 無効化する前に、**Route 53** で axiola.jp と www.axiola.jp の A レコードを **CloudFront のエイリアスから Lightsail の静的 IP（またはロードバランサー）に変更** しておくと、無効化〜削除のあいだもサイトが Lightsail 直で表示されます。無効化だけ先に行い、DNS は後から変えてもかまいません。

### HTTPS が有効にならない・証明書が検証されない

- **方法 A（Lightsail）**: Lightsail の証明書画面で、**検証用 CNAME** を Route 53 に正しく追加したか確認。証明書の状態が「検証済み」になってから、ロードバランサーに証明書をアタッチする。Route 53 の A レコード／エイリアスが、Lightsail ロードバランサーを指しているか確認する。
- **方法 B（ACM + CloudFront）**: CloudFront にアタッチする ACM 証明書は **us-east-1** で発行されているか確認。Route 53 の A レコードが **エイリアス** で CloudFront ディストリビューションを指しているか確認する。オリジンに IP を指定している場合は、CloudFront のオリジンリクエストで **Host ヘッダー** をカスタムドメイン（例: `axiola.jp`）に設定すると、WordPress のリダイレクトが正しく動作しやすい。
- **方法 C（Let's Encrypt）**: 下記「bncert で Let's Encrypt が失敗する（tls-alpn-01）」を参照。証明書の自動更新に失敗している場合は [Lightsail の bncert 更新手順](https://repost.aws/knowledge-center/lightsail-bitnami-renew-ssl-certificate) を参照。

### bncert で Let's Encrypt が失敗する（tls-alpn-01・Deactivating auth）

**「acme: use tls-alpn-01 solver」のあと「Deactivating auth」や「An error occurred creating certificates」** となる場合、bncert が使う **TLS-ALPN-01** チャレンジが失敗しています。Let's Encrypt は **ポート 443** でインスタンスに直接接続して検証するため、次の 2 点を確認してください。

1. **Lightsail のファイアウォールで 443（HTTPS）を開放する**
   - **Lightsail コンソール** → 対象インスタンスを開く → **「ネットワーク」**（Networking）タブをクリック。
   - **「IPv4 ファイアウォール」** で、**HTTPS（443）** が **0.0.0.0/0**（すべての IPv4）から許可されているか確認する。
   - **443 が一覧にない場合**: **「+ ルールを追加」**（または Add rule）で、**アプリケーション**: HTTPS、**プロトコル**: TCP、**ポート**: 443、**ソース**: 0.0.0.0/0（または「Anywhere」）を追加して保存する。
   - デフォルトでは SSH（22）と HTTP（80）だけが開いており、**443 が閉じていると tls-alpn-01 は必ず失敗**します。

2. **Route 53 の A レコードが Lightsail の静的 IP を向いているか**
   - **方法 B（CloudFront）** から方法 C に切り替えた場合、**axiola.jp** と **www.axiola.jp** の A レコードが **CloudFront のエイリアス** のままになっていないか確認する。
   - Let's Encrypt は **ドメイン名で解決される先**（＝A レコードの向き先）に 443 で接続します。CloudFront やロードバランサーを向いていると、bncert が動いている Lightsail インスタンスには届かず検証に失敗します。
   - **対処**: Route 53 で **axiola.jp** と **www.axiola.jp** の A レコードの値を、**Lightsail インスタンスにアタッチした静的 IP** に変更する（エイリアスを外し、A レコードで静的 IP を直接指定）。DNS 伝播（数分〜最大 48 時間）を待ってから、再度 `sudo /opt/bitnami/bncert-tool` を実行する。

**「no valid A records found for axiola.jp」と出る場合**（www は検証成功しているがルートだけ失敗）: **ルートドメイン（axiola.jp）** の A レコードが不足しているか、**エイリアスで CloudFront を指したまま** になっています。Route 53 のホストゾーンで **レコード名が空（または `@`）の A レコード** を確認し、**値** を **Lightsail の静的 IP** にすること。エイリアスの場合は「エイリアス」をオフにして、**A レコード** で **IPv4 アドレス** に静的 IP を直接入力する。ルート用の A レコードが無い場合は **「レコードを作成」** で、レコード名を空（ルート）、タイプ **A**、値に **Lightsail の静的 IP** を指定して作成する。保存後、数分〜最大 48 時間待ってから bncert-tool を再実行する。

上記を直したあと、**数分待ってから** bncert-tool を再実行してください。

### 「SPF レコードを追加してください」「Action Needed: SPF record」と表示される

WP Mail SMTP などで **「It doesn't look like the SPF record required by your SMTP server has been added to your domain」**（Action Needed）と出る場合、または **bounce.axiola.jp などサブドメインにだけ SPF がある** 場合は、**ルートドメイン（axiola.jp）** 用の SPF が不足しています。送信元が `*@axiola.jp` のときは **ルート** に SPF が必要です。SES 利用時は **Route 53** でルート用の SPF を設定します。

**「指定された名前のレコードは既に存在します」(Tried to create resource record set [name='axiola.jp.', type='TXT']) と出る場合**: ルートには **既に TXT レコード** があります。**新規作成せず、既存の TXT を編集** してください。一覧で **レコード名が空**（または `axiola.jp.`）で **タイプが TXT** の行を選び **「編集」** する。既存の値が **`v=spf1` で始まる SPF** なら、その文字列に **`include:amazonses.com`** を追加して保存（例: `"v=spf1 ~all"` → `"v=spf1 include:amazonses.com ~all"`）。**既存が google-site-verification など SPF 以外だけの場合**: Route 53 では **同じ名前・同じタイプ（TXT）のレコードに、値を複数行で持てます**。編集画面の **値** の欄に、**既存の 1 行（Google 認証用）はそのまま** にし、**2 行目** として **`"v=spf1 include:amazonses.com ~all"`** を追加して保存する。これで 1 本の TXT レコードに「Google 認証」と「SPF」の両方が入り、新規レコードは不要です。

1. **Route 53**（infrastructure アカウント）→ **ホストゾーン** → 対象ドメイン（例: **axiola.jp**）のゾーンを開く。
2. **レコード名が空**（ルート）の **TXT** があるか確認。あれば **「編集」**、無ければ **「レコードを作成」**。
3. 次を設定する:
   - **レコード名**: 空欄のまま（ルートドメイン用。axiola.jp の SPF にするため）
   - **レコードタイプ**: **TXT**
   - **値**: `"v=spf1 include:amazonses.com ~all"`  
     （ダブルクォートも含めて 1 行で入力。既にルート用の SPF TXT がある場合は、そのレコードを編集し、`include:amazonses.com` を既存の `v=spf1 ...` に追加して 1 本にまとめる。）
   - **TTL**: `300` など
4. **「レコードを作成」** で保存する。

追加後、**数分〜最大 48 時間** DNS が伝播すると、WP Mail SMTP の案内は解消されます。詳細は本文 **「3. Route 53 に SPF を追加」** を参照してください。

### 方法 C で wp-config の WP_HOME / WP_SITEURL をデフォルトに戻す

**方法 B（CloudFront）で追加した** `define( 'WP_HOME', ... );` と `define( 'WP_SITEURL', ... );` を **方法 C** では使わず、通常の管理画面での設定に戻したい場合:

1. Lightsail に SSH でログインし、`/opt/bitnami/wordpress/wp-config.php` を編集する。
2. **X-Forwarded-Proto 用の if ブロック**（`if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])...` で始まる数行）と、**`define( 'WP_HOME', ... );`** および **`define( 'WP_SITEURL', ... );`** の 2 行を **削除** する。
3. 保存後、WordPress の **設定 → 一般** で **WordPress アドレス (URL)** と **サイトアドレス (URL)** を **`https://axiola.jp`**（または運用するドメイン）に設定し、**変更を保存** する。

これで管理画面の入力欄が編集可能になり、今後の変更は画面から行えます。方法 C ではリクエストの Host がそのまま axiola.jp になるため、define は不要です。

### https://axiola.jp を開くと https://127.0.0.1 にリダイレクトされて 404 になる

**原因**: WordPress の **サイトアドレス** がデータベース上で **`https://127.0.0.1`**（または `http://127.0.0.1`）になっており、リダイレクトやリンクがすべて 127.0.0.1 を指しています。ローカル環境で構築した DB をそのまま本番に載せた場合などに起こりがちです。

**対処（Lightsail に SSH でログインして実行）**

1. **現在の値を確認する**
   ```bash
   cd /opt/bitnami/wordpress
   sudo -u daemon wp option get siteurl
   sudo -u daemon wp option get home
   ```
   （Bitnami では `wp` を `daemon` ユーザーで実行する必要がある場合があります。`wp` がパスに無い場合は `/opt/bitnami/wordpress/bin/wp` などフルパスで実行。）

2. **`https://axiola.jp` に更新する**
   ```bash
   sudo -u daemon wp option update siteurl 'https://axiola.jp'
   sudo -u daemon wp option update home 'https://axiola.jp'
   ```
   ドメインが axiola.jp でない場合は、実際のドメインに読み替えてください。末尾のスラッシュは付けなくてかまいません。

3. **wp-config.php で上書きされていないか確認する**
   - `wp-config.php` に **`define( 'WP_HOME', 'https://127.0.0.1' );`** や **`define( 'WP_SITEURL', 'https://127.0.0.1' );`** が無いか確認する。あれば **削除** するか、**`https://axiola.jp`** に書き換える。define が無ければ、上記の DB 更新だけで反映されます。

4. ブラウザで **https://axiola.jp** を開き直す（キャッシュを無効にして再読み込みするか、シークレットウィンドウで確認）。

**WP-CLI が使えない場合**: phpMyAdmin や MySQL クライアントで **`wp_options`** テーブルを開き、**option_name** が **`siteurl`** および **`home`** の行の **option_value** を **`https://axiola.jp`** に変更して保存する。

### HTTPS にしたあと CSS が効かない・スタイルが読み込まれない

**原因**: WordPress が CSS やスクリプトの URL を **http://** で出力しているため、HTTPS のページから読み込もうとして **混合コンテンツ** になったり、パスがずれて読み込めていません。多くの場合、**サイトアドレスがまだ http のまま** です。

**対処（順に試す）**

1. **WordPress の URL を https に変更する**
   - 管理画面（`https://axiola.jp/wp-admin` など）にログインする。
   - **設定** → **一般** を開く。
   - **WordPress アドレス (URL)** と **サイトアドレス (URL)** の両方を **`https://axiola.jp`**（または `https://www.axiola.jp`）に変更し、**変更を保存** する。
   - **項目がグレーアウトして編集できない場合**: `wp-config.php` で **`WP_HOME`** と **`WP_SITEURL`** が定義されていると、画面上の入力欄が無効になります。このときは **サーバー上で `wp-config.php` を編集** する。Lightsail の WordPress（Bitnami）に SSH でログインし、`/opt/bitnami/wordpress/wp-config.php` を開く。
     - **WordPress のデフォルト** は、**define を書かない** ことです。URL はデータベース（管理画面の「設定」→「一般」で設定した値）から読まれます。**方法 C（インスタンス直）** の場合は、**define の 2 行を削除** し、管理画面で `https://axiola.jp` を設定するだけで十分です（「[方法 C で wp-config の WP_HOME / WP_SITEURL をデフォルトに戻す](#方法-c-で-wp-config-の-wp_home--wp_siteurl-をデフォルトに戻す)」参照）。
     - define を**残す**場合（方法 B の CloudFront 経由など）: **`http://` で始まっている定義** は **問題あり** です。**必ず `https://` に変更** し、次のいずれかにする。
       - **axiola.jp と www の両方** を同じ設定で扱う場合（ホストを動的にする）:
         ```php
         define( 'WP_HOME', 'https://' . $_SERVER['HTTP_HOST'] . '/' );
         define( 'WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST'] . '/' );
         ```
       - **ルートドメインだけ** に固定する場合:
         ```php
         define( 'WP_HOME', 'https://axiola.jp/' );
         define( 'WP_SITEURL', 'https://axiola.jp/' );
         ```
     - 保存後、WordPress のキャッシュプラグインを使っている場合はキャッシュをクリアし、ブラウザでフロントをハードリロードする。
   - フロントのページを **ハードリロード**（Ctrl+Shift+R や Cmd+Shift+R）するか、シークレットウィンドウで開き直して CSS が反映されるか確認する。

2. **変更しても効かない場合 — データベースの URL を確認する**
   - 設定画面で https にしたあとも、DB に古い `http://` が残っていることがあります。Lightsail の WordPress に SSH で入れる場合、次のように確認する。
     ```bash
     # Bitnami の場合（パスは環境に合わせる）
     cd /opt/bitnami/wordpress && wp option get siteurl
     wp option get home
     ```
   - どちらかが `http://` のままなら、`wp option update siteurl 'https://axiola.jp'` および `wp option update home 'https://axiola.jp'` で更新する（ドメインは実際のものに合わせる）。WP-CLI が無い場合は、phpMyAdmin や MySQL クライアントで `wp_options` の `siteurl` と `home` を https に書き換える。

3. **CloudFront のキャッシュを無効化する**
   - 方法 B（CloudFront）を使っている場合、古い **http** の URL でキャッシュされた HTML が返っている可能性があります。**CloudFront** コンソールで該当ディストリビューションを開き、**「無効化」** タブから **無効化を作成** し、オブジェクトパスに **`/*`** を指定して実行する。数分後に再度ページを開き直して確認する。

4. **ブラウザの開発者ツールで確認する**
   - ページを開いた状態で F12 → **Network** タブで CSS ファイルのリクエストを確認する。**Status がブロック（mixed content）や 404** になっていないか、**Request URL** が `http://` になっていないかを見る。`http://` で出ている場合は 1〜2 で URL を https に直す。

### シンプルな構成にしたい場合（axiola.jp のみで運用）

**www を使わず、axiola.jp（ルートドメイン）だけ** で運用すると、CloudFront Function や www 用のリダイレクトが不要で構成が簡単です。

- **CloudFront**: 代替ドメイン名（CNAME）は **axiola.jp のみ** にする。**www.axiola.jp は一覧から削除** する。
- **Route 53**: **ルート（axiola.jp）の A レコードだけ** をこの CloudFront にエイリアスする。**www の A レコードは削除** するか、company-hp 用 CloudFront には向けない（他サービス用なら別の向き先のまま）。
- **wp-config.php**: 上記「対処」のとおり、**X-Forwarded-Proto の処理** と **`WP_HOME` / `WP_SITEURL` を固定 `https://axiola.jp/`** にする。
- **オリジン**: 追加ヘッダーで **X-Forwarded-Proto: https** を付与する（前述の方法 A）。

これで **https://axiola.jp** だけでサイトを公開します。www には別の用途で使うか、案内用に「axiola.jp でアクセスしてください」と記載する程度にします。CloudFront Function は不要です。

---

### ERR_TOO_MANY_REDIRECTS / リダイレクトが繰り返し行われました（www.axiola.jp など）

**原因**: CloudFront が HTTPS を終端し、オリジン（Lightsail）には **HTTP** で転送しています。WordPress は「いまのリクエストは HTTP だ」と判断し、`WP_HOME` が `https://` のため「HTTPS にリダイレクトしよう」とします。ブラウザはすでに HTTPS で開いているので、同じ URL にまたリダイレクトされ、**リダイレクトループ** になります。**シンプルにしたい場合は上記「axiola.jp のみで運用」にすると、www のループは発生しません。**

**重要**: **`WP_HOME` / `WP_SITEURL` を `https://` にしただけではループは止まりません。** 必ず **「X-Forwarded-Proto を検知して `$_SERVER['HTTPS']` を立てる処理」** を **`define( 'WP_HOME' / 'WP_SITEURL' ) より上（前）** に追加してください。以下のブロックを **1 まとまりで** `wp-config.php` に書き、既存の `WP_HOME` / `WP_SITEURL` の定義はこのブロックに置き換えます。

**対処（wp-config.php の編集）**: Lightsail に SSH でログインし、`/opt/bitnami/wordpress/wp-config.php` を開く。**`require_once(ABSPATH . 'wp-settings.php');` より前** に、次のブロックを **この順序のまま** 追加する（既に `WP_HOME` / `WP_SITEURL` がある場合は、次のブロックに差し替える）。

**注意**: オリジンが **origin.axiola.jp** の場合、WordPress に届く **Host は `origin.axiola.jp`** です。**必ず固定で `https://axiola.jp/` を指定** してください。**シンプルにしたいとき** は、上記「axiola.jp のみで運用」のとおり **www を CloudFront と Route 53 から外せば、CloudFront Function は不要** です。**www も使いたい場合のみ**、下記「それでもループが解消しない場合」の CloudFront Function を追加します。

```php
/* CloudFront 経由で HTTPS を正しく検知する（リダイレクトループ防止）。必ず define より上に書く */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

/* オリジンは origin.axiola.jp のため Host は origin.axiola.jp。固定で正規 URL を指定する */
define( 'WP_HOME', 'https://axiola.jp/' );
define( 'WP_SITEURL', 'https://axiola.jp/' );
```

- 1 行目〜4 行目: CloudFront が付与する **X-Forwarded-Proto** が `https` のときに **`$_SERVER['HTTPS'] = 'on'`** を設定する。
- 6〜8 行目: サイト URL は **固定で `https://axiola.jp/`**。**`$_SERVER['HTTP_HOST']` は使わない**（オリジンに届く Host は origin.axiola.jp のため）。www も使う場合は下記の CloudFront Function を追加する。

保存後、**CloudFront のキャッシュ無効化**（パス `/*`）を実行し、ブラウザの **Cookie を削除** するか **シークレットウィンドウ** で `https://www.axiola.jp` を開き直す。

**まだループする場合**: CloudFront は **デフォルトではオリジンに X-Forwarded-Proto を送りません**。そのため、次のいずれかでオリジンに「HTTPS で来た」と伝える必要があります。

- **方法 A（推奨）: オリジンにカスタムヘッダーを追加する**  
  **CloudFront** コンソール → 該当ディストリビューション → **オリジン** タブ → オリジン（例: origin.axiola.jp）を選択 → **編集**。**「追加ヘッダー」**（または Custom headers）で **名前** `X-Forwarded-Proto`、**値** `https` を 1 件追加して保存。これで CloudFront からオリジンへのリクエストに常に `X-Forwarded-Proto: https` が付き、wp-config の `$_SERVER['HTTPS'] = 'on'` が効く。

- **方法 B: オリジンリクエストポリシーで CloudFront のヘッダーを転送する**  
  マネージドポリシー **AllViewerAndCloudFrontHeaders-2022-06** など、CloudFront が付与するヘッダーをオリジンに転送するポリシーを選ぶ。ポリシーに **CloudFront-Viewer-Proto** や **X-Forwarded-Proto** 相当が含まれるか確認する。含まれない場合は、オリジン側（wp-config）で `$_SERVER['HTTP_CLOUDFRONT_VIEWER_PROTO'] === 'https'` を検知して `$_SERVER['HTTPS'] = 'on'` を設定するように書き換える必要がある。

まず **方法 A** を試すとよいです。

**www も使いたい場合**（axiola.jp と www.axiola.jp の両方でアクセスできるようにする）

**シンプルな構成でよい場合は、上記「axiola.jp のみで運用」にすると、CloudFront Function は不要です。** 以下は **www も有効にしたい場合だけ** 実施します。**www のアクセスを CloudFront の段階でルートドメイン（axiola.jp）に 301 リダイレクト** し、WordPress には **常に axiola.jp だけ** を渡す構成にします。

**手順の概要**

1. **wp-config.php で正規 URL をルートドメインだけに固定する**  
   `WP_HOME` / `WP_SITEURL` を **`https://axiola.jp/`** に固定し、`$_SERVER['HTTP_HOST']` は使わない。  
   （X-Forwarded-Proto のブロックはそのまま残す。）

2. **CloudFront で www → ルートの 301 リダイレクトを追加する**  
   **CloudFront Functions** を使い、**ビューアーリクエスト** で Host が `www.axiola.jp` のときだけ、`https://axiola.jp` + パス へ 301 で返す。これで www のリクエストは **オリジン（WordPress）に届かず** にリダイレクトされるため、ループしません。

**1. wp-config.php の変更**

既存の `define( 'WP_HOME', ... );` と `define( 'WP_SITEURL', ... );` を、次の 2 行に **置き換え** する（X-Forwarded-Proto の if ブロックはそのまま）。

```php
define( 'WP_HOME', 'https://axiola.jp/' );
define( 'WP_SITEURL', 'https://axiola.jp/' );
```

**2. CloudFront Function で www を axiola.jp に 301 リダイレクト**

1. **CloudFront** コンソール → **Functions**（左メニュー）→ **Create function**。**名前** を入力（例: `redirect-www-to-apex`）。
2. **Build** タブのエディタに以下を貼り付ける。

```javascript
function handler(event) {
    var request = event.request;
    var host = request.headers.host ? request.headers.host.value : '';
    if (host === 'www.axiola.jp') {
        var path = request.uri || '/';
        var qs = request.querystring;
        if (qs && Object.keys(qs).length > 0) {
            var parts = [];
            for (var k in qs) {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(qs[k].value));
            }
            path += '?' + parts.join('&');
        }
        return {
            statusCode: 301,
            statusDescription: 'Moved Permanently',
            headers: { location: { value: 'https://axiola.jp' + path } }
        };
    }
    return request;
}
```

3. **Save changes** → **Publish**。
4. **Associate** タブ → **Add association**。**Distribution** に company-hp 用のディストリビューション、**Event type** に **Viewer request** を選び、**Add association**。
5. **CloudFront のキャッシュ無効化**（パス `/*`）を実行する。

これで `https://www.axiola.jp/` にアクセスすると、CloudFront が **WordPress に転送する前に** `https://axiola.jp/` へ 301 し、ループしません。`https://axiola.jp/` は従来どおり CloudFront → オリジン → WordPress で表示されます。

**補足**: 正規を **www にしたい** 場合は、上記の Function を「Host が `axiola.jp` のとき `https://www.axiola.jp` に 301」に書き換え、`WP_HOME` / `WP_SITEURL` を `https://www.axiola.jp/` にすれば同様に構成できます。

---

## 付録 A. 共通証明書（axiola.jp + *.axiola.jp）への切り替え — tools と iq-test の CloudFront

us-east-1 で **axiola.jp と \*.axiola.jp の両方を含む新しい ACM 証明書** を発行した場合、**company-hp** の CloudFront だけでなく、**tools**（tools.axiola.jp / tools-stg.axiola.jp）と **iq-test**（stg-iq.axiola.jp / iq.axiola.jp）の CloudFront でも、その証明書に切り替える必要があります。いずれも **CloudFront の「カスタム SSL 証明書」をコンソールで差し替える** だけでよく、Terraform の変数変更は不要です（CloudFront の証明書は手動設定の想定）。

### A.1 前提

- **us-east-1** で **axiola.jp** と **\*.axiola.jp** を含む ACM 証明書を発行・検証済みであること（本ドキュメント「1.4.4a ACM でルート＋ワイルドカード用の証明書を用意する」を参照）。
- 以下で切り替えるのは **CloudFront に紐づく証明書** のみ。**iq-test の ALB** で使っている ap-northeast-1 の証明書（`alb_certificate_arn`）は変更しません。

### A.2 tools の CloudFront 証明書を切り替える

1. **AWS コンソール** で、tools の CloudFront を管理している **アカウント** にログインする（本番: Production、ステージング: Staging など、環境ごとにアカウントが分かれている場合はそれぞれで実施）。
2. リージョンを **グローバル** のまま **CloudFront** を開く。
3. **ディストリビューション** 一覧から、次のドメインを配信しているディストリビューションを特定する。
   - **本番**: 代替ドメイン名に **tools.axiola.jp** が含まれるもの。
   - **ステージング**: 代替ドメイン名に **tools-stg.axiola.jp** が含まれるもの。
4. 該当ディストリビューションの **ID** をクリックして詳細を開く。
5. **「一般」** タブの **「編集」** をクリックする。
6. **「カスタム SSL 証明書」** で、**us-east-1** に発行した **axiola.jp と \*.axiola.jp を含む証明書** を選択する。
7. **「変更を保存」** をクリックする。反映に数分かかることがある。
8. ステージング用のディストリビューションがある場合は、同様に **tools-stg.axiola.jp** 用のディストリビューションでも 4〜7 を繰り返す。

**確認**: ブラウザで **https://tools.axiola.jp**（および **https://tools-stg.axiola.jp**）を開き、証明書が新しいものであること・警告が出ないことを確認する。

### A.3 iq-test の CloudFront 証明書を切り替える

1. **AWS コンソール** で、iq-test の CloudFront を管理している **アカウント** にログインする（staging 用と本番用でアカウントが分かれている場合はそれぞれで実施）。
2. **CloudFront** を開く。
3. **ディストリビューション** 一覧から、次のドメインを配信しているディストリビューションを特定する。
   - **Staging**: 代替ドメイン名に **stg-iq.axiola.jp** が含まれるもの。
   - **本番**: 代替ドメイン名に **iq.axiola.jp** が含まれるもの。
4. 該当ディストリビューションの **ID** をクリックして詳細を開く。
5. **「一般」** タブの **「編集」** をクリックする。
6. **「カスタム SSL 証明書」** で、**us-east-1** に発行した **axiola.jp と \*.axiola.jp を含む証明書** を選択する。
7. **「変更を保存」** をクリックする。
8. もう一方の環境（staging または本番）のディストリビューションがある場合も、同様に 3〜7 を繰り返す。

**注意**: iq-test の **ALB** に設定している **ap-northeast-1** の証明書（Terraform の `alb_certificate_arn`）は **変更しない**。CloudFront 用の us-east-1 証明書の切り替えのみ行う。

**確認**: **https://stg-iq.axiola.jp** および **https://iq.axiola.jp** でアクセスし、証明書が新しいものであること・動作に問題ないことを確認する。

### A.4 チェックリスト（tools / iq-test の証明書切り替え）

| 対象 | ドメイン | 実施内容 |
|------|----------|----------|
| tools 本番 | tools.axiola.jp | CloudFront の「カスタム SSL 証明書」を新証明書（axiola.jp + \*.axiola.jp）に変更 |
| tools ステージング | tools-stg.axiola.jp | 上記と同様 |
| iq-test Staging | stg-iq.axiola.jp | CloudFront の「カスタム SSL 証明書」を新証明書に変更 |
| iq-test 本番 | iq.axiola.jp | 上記と同様 |
| iq-test ALB | （ap-northeast-1） | **変更しない**（alb_certificate_arn はそのまま） |

---

## 7. 参考リンク

- [LIGHTSAIL_DEPLOY.md](./LIGHTSAIL_DEPLOY.md) … Lightsail 全体の構築・Contact Form 7 の基本
- [Lightsail の SSL/TLS 証明書](https://docs.aws.amazon.com/ja_jp/lightsail/latest/userguide/understanding-tls-ssl-certificates-in-lightsail-https.html) / [証明書をロードバランサーにアタッチ](https://docs.aws.amazon.com/ja_jp/lightsail/latest/userguide/attach-validated-certificate-to-load-balancer.html)
- [Lightsail で WordPress を HTTPS 化（bncert / Let's Encrypt）](https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-enabling-https-on-wordpress.html) … 方法 C（追加費用 $0）
- [CloudFront でカスタム SSL 証明書を使用する（ACM）](https://docs.aws.amazon.com/ja_jp/AmazonCloudFront/latest/DeveloperGuide/cnames-and-ssl.html) … 既存の ACM 証明書を CloudFront で使う場合
- [Amazon SES の SMTP インターフェース](https://docs.aws.amazon.com/ja_jp/ses/latest/dg/send-email-smtp.html)
- [SES のドメイン認証（DKIM）](https://docs.aws.amazon.com/ja_jp/ses/latest/dg/send-email-authentication-dkim.html)
- [Route 53 でホストゾーンを作成](https://docs.aws.amazon.com/ja_jp/Route53/latest/DeveloperGuide/CreatingHostedZone.html)
