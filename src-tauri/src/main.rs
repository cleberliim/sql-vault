// Esconde a janela de console do próprio app em builds de release
// (`npm run build`). Em builds de debug (`npm run dev`) o console
// continua aparecendo, pra você poder ver os println! de diagnóstico.
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

// SQL Vault - shell Tauri
//
// Estratégia: ao iniciar, sobe o servidor embutido do PHP
// (`php -S 127.0.0.1:<porta> index.php`) como processo filho, apontando
// para a raiz do projeto (onde está o index.php/roteador), e abre a
// janela do Tauri carregando essa URL local. Ao fechar a janela, o
// processo PHP é encerrado.
//
// IMPORTANTE: isso requer PHP 8.3+ disponível no PATH da máquina onde
// o executável rodar. Para distribuir sem depender de instalação prévia
// de PHP, substitua o Command::new("php") abaixo por um binário PHP
// portátil (ex.: PHP para Windows embarcado) registrado como "sidecar"
// do Tauri em tauri.conf.json.

use std::net::TcpListener;
use std::process::{Child, Command, Stdio};
use std::sync::Mutex;
use tauri::{Manager, WebviewUrl, WebviewWindowBuilder};

#[cfg(target_os = "windows")]
use std::os::windows::process::CommandExt;

// Flag do Windows que impede o processo filho (php.exe) de abrir sua
// própria janela de console, já que ele é um executável "console" por
// padrão. https://learn.microsoft.com/windows/win32/procthread/process-creation-flags
#[cfg(target_os = "windows")]
const CREATE_NO_WINDOW: u32 = 0x08000000;

struct PhpServer(Mutex<Option<Child>>);

/// Reserva uma porta TCP livre para o servidor PHP embutido.
fn find_free_port() -> u16 {
    TcpListener::bind("127.0.0.1:0")
        .expect("Não foi possível reservar uma porta livre")
        .local_addr()
        .unwrap()
        .port()
}

/// No Windows, `resource_dir()`/`canonicalize()` frequentemente retornam
/// caminhos com o prefixo de "caminho estendido" `\\?\` (ex.:
/// `\\?\C:\Users\...`). O `CreateProcess` do Windows lida bem com isso para
/// abrir o `php.exe`, mas o PHP internamente (ao montar o caminho de cada
/// extensão a partir de `extension_dir` e chamar `LoadLibrary`) NÃO lida bem
/// com esse prefixo, e silenciosamente falha ao carregar extensões como
/// `pdo_sqlite` — mesmo com o arquivo/pasta existindo de verdade. Por isso,
/// removemos esse prefixo de qualquer caminho que for repassado como
/// argumento/config para o processo do PHP.
fn strip_extended_prefix(path: &std::path::Path) -> std::path::PathBuf {
    let s = path.to_string_lossy();
    match s.strip_prefix(r"\\?\") {
        Some(stripped) => std::path::PathBuf::from(stripped),
        None => path.to_path_buf(),
    }
}

fn main() {
    tauri::Builder::default()
        .setup(|app| {
            let port = find_free_port();

            // Em dev (`tauri dev`), os resources declarados em tauri.conf.json
            // ainda não foram copiados para lugar nenhum — o projeto é usado
            // direto da árvore de código (diretório atual). Em produção
            // (`tauri build`), index.php/app/routes/config/assets já foram
            // copiados para resource_dir() pelo bundler (ver
            // tauri.conf.json > bundle.resources).
            let project_root = if cfg!(debug_assertions) {
                std::env::current_dir().unwrap()
            } else {
                app.path()
                    .resource_dir()
                    .unwrap_or_else(|_| std::env::current_dir().unwrap())
            };
            let project_root = strip_extended_prefix(&project_root);

            // O banco SQLite precisa de uma pasta com permissão de escrita.
            // A pasta de instalação (resource_dir, ex.: Program Files) é
            // somente leitura em produção, então usamos a pasta de dados do
            // usuário (%APPDATA%\com.cleberlima.sqlvault no Windows) e
            // repassamos para o PHP via variável de ambiente.
            let data_dir = app
                .path()
                .app_data_dir()
                .expect("Não foi possível resolver a pasta de dados do usuário");
            let data_dir = strip_extended_prefix(&data_dir);
            std::fs::create_dir_all(&data_dir)
                .expect("Não foi possível criar a pasta de dados do usuário");

                // Localiza o PHP
                let php = if cfg!(debug_assertions) {
                    std::env::current_dir()
                        .unwrap()
                        .join("src-tauri")
                        .join("php")
                        .join("php.exe")
                } else {
                    project_root.join("php").join("php.exe")
                };
                let php = strip_extended_prefix(&php);

                // Localiza o php.ini
                let php_ini = if cfg!(debug_assertions) {
                    std::env::current_dir()
                        .unwrap()
                        .join("src-tauri")
                        .join("php")
                } else {
                    project_root.join("php")
                };
                let php_ini = strip_extended_prefix(&php_ini);

                let extension_dir = strip_extended_prefix(&php_ini.join("ext"));

                // Log em arquivo em vez de console (a janela do app agora
                // fica escondida em release). Fica em
                // <pasta de dados do usuário>\php-server.log — abra esse
                // arquivo se precisar debugar algo no futuro.
                let log_path = data_dir.join("php-server.log");
                let mut log_file_out = std::fs::File::create(&log_path)
                    .expect("Não foi possível criar o arquivo de log do PHP.");
                {
                    use std::io::Write;
                    let _ = writeln!(log_file_out, "PHP encontrado em: {:?}", php);
                    let _ = writeln!(log_file_out, "Existe? {}", php.exists());
                    let _ = writeln!(log_file_out, "extension_dir (absoluto): {:?}", extension_dir);
                    let _ = writeln!(log_file_out, "extension_dir existe? {}", extension_dir.exists());
                }
                let log_file_err = log_file_out
                    .try_clone()
                    .expect("Não foi possível duplicar o handle do log.");

                let mut cmd = Command::new(&php);
                cmd.arg("-c")
                    .arg(&php_ini)
                    .arg("-d")
                    .arg(format!("extension_dir={}", extension_dir.display()))
                    .arg("-S")
                    .arg(format!("127.0.0.1:{port}"))
                    .arg("index.php")
                    .current_dir(&project_root)
                    .env("VAULT_DB_DIR", &data_dir)
                    .stdout(Stdio::from(log_file_out))
                    .stderr(Stdio::from(log_file_err));

                // No Windows, impede que o php.exe abra sua própria janela
                // de console preta.
                #[cfg(target_os = "windows")]
                cmd.creation_flags(CREATE_NO_WINDOW);

                let child = cmd.spawn().expect("Falha ao iniciar o PHP.");

            app.manage(PhpServer(Mutex::new(Some(child))));

            let url = format!("http://127.0.0.1:{port}")
                .parse()
                .expect("URL inválida gerada para o servidor local");

            WebviewWindowBuilder::new(app, "main", WebviewUrl::External(url))
                .title("SQL Vault")
                .inner_size(1000.0, 700.0)
                .min_inner_size(860.0, 560.0)
                .center()
                .build()?;

            Ok(())
        })
        .on_window_event(|window, event| {
            // Encerra o processo PHP quando a janela principal é destruída.
            if let tauri::WindowEvent::Destroyed = event {
                if let Some(state) = window.app_handle().try_state::<PhpServer>() {
                    if let Some(mut child) = state.0.lock().unwrap().take() {
                        let _ = child.kill();
                    }
                }
            }
        })
        .run(tauri::generate_context!())
        .expect("erro ao executar a aplicação Tauri");
}