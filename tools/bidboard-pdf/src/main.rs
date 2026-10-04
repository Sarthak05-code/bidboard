use headless_chrome::types::PrintToPdfOptions;
use headless_chrome::{Browser, LaunchOptions};
use serde::Deserialize;
use std::env;
use std::fs;
use std::path::Path;

#[derive(Deserialize, Debug)]
struct Bid {
    task_title: String,
    client_name: String,
    proposed_price: String,
    status: String,
    submitted_at: String,
}

#[derive(Deserialize, Debug)]
struct Payload {
    email: String,
    output_path: String,
    bids: Vec<Bid>,
}

fn escape_html(s: &str) -> String {
    s.replace('&', "&amp;") // must be first
        .replace('<', "&lt;")
        .replace('>', "&gt;")
        .replace('"', "&quot;")
        .replace('\'', "&#39;")
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let args: Vec<String> = env::args().collect();
    if args.len() < 2 {
        eprintln!("Usage: bidboard-pdf <json_payload_or_path>");
        std::process::exit(1);
    }

    let input_arg = &args[1];
    let json_data = if fs::metadata(input_arg).is_ok() {
        fs::read_to_string(input_arg)?
    } else {
        input_arg.clone()
    };

    let payload: Payload = serde_json::from_str(&json_data)?;

    // 1. Build table row HTML strings dynamically
    let mut rows_html = String::new();
    let mut accepted_val = 0.0;

    for bid in &payload.bids {
        let status = bid.status.trim().to_lowercase();
        let badge_class = match status.as_str() {
            "accepted" => {
                let price_clean: f64 = bid.proposed_price.replace(",", "").parse().unwrap_or(0.0);
                accepted_val += price_clean;
                "badge-accepted"
            }
            "rejected" => "badge-rejected",
            _ => "badge-pending",
        };

        rows_html.push_str(&format!(
            "<tr>
                <td style='font-weight:600;'>{}</td>
                <td>{}</td>
                <td class='price'>Rs. {}</td>
                <td><span class='badge {}'>{}</span></td>
                <td style='color:#6b7280;'>{}</td>
            </tr>",
            escape_html(&bid.task_title),
            escape_html(&bid.client_name),
            escape_html(&bid.proposed_price),
            badge_class,
            escape_html(&status),
            escape_html(&bid.submitted_at)
        ));
    }

    // 2. Load and hydrate the HTML template
    let template_path = Path::new("tools/bidboard-pdf/template.html");
    let template_str = if template_path.exists() {
        fs::read_to_string(template_path)?
    } else {
        // Fallback to reading relative to root
        fs::read_to_string("template.html")?
    };

    let final_html = template_str
        .replace("{{EMAIL}}", &escape_html(&payload.email))
        .replace("{{TOTAL_BIDS}}", &payload.bids.len().to_string())
        .replace("{{TOTAL_VALUE}}", &format!("{:.2}", accepted_val))
        .replace("{{ROWS}}", &rows_html);

    // Save temporary HTML file
    let temp_html_path = format!("{}.html", payload.output_path);
    fs::write(&temp_html_path, &final_html)?;

    // 3. Render HTML to PDF using Headless Chrome in Rust
    let options = LaunchOptions::default_builder().headless(true).build()?;

    let browser = Browser::new(options)?;
    let tab = browser.new_tab()?;

    // Convert path to canonical Windows format and replace backslashes with forward slashes
    let abs_path = fs::canonicalize(&temp_html_path)?;
    let path_str = abs_path.to_string_lossy().replace("\\", "/");

    // Strip verbatim prefix (\\?\) if present on Windows
    let clean_path = if path_str.starts_with("//?/") {
        &path_str[4..]
    } else if path_str.starts_with("/?/") {
        &path_str[3..]
    } else {
        &path_str
    };

    let absolute_html_url = format!("file:///{}", clean_path.trim_start_matches('/'));
    tab.navigate_to(&absolute_html_url)?;
    tab.wait_until_navigated()?;

    let pdf_bytes = tab.print_to_pdf(Some(PrintToPdfOptions {
        print_background: Some(true),
        ..Default::default()
    }))?;
    fs::write(&payload.output_path, pdf_bytes)?;

    // Clean up temp HTML file
    let _ = fs::remove_file(temp_html_path);

    println!("SUCCESS");
    Ok(())
}
