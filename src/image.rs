use std::fs::{copy, create_dir_all, exists};

use actix_multipart::form::{MultipartForm, tempfile::TempFile};
use actix_web::HttpResponse;
use serde::Serialize;

use crate::{
    Response,
    random::{self, generate_random_sequence},
};

#[derive(MultipartForm)]
pub struct ImageUpload {
    pub file: TempFile,
}

#[derive(Serialize)]
struct ImageData {
    pub id: String,
}

pub async fn handle_image_upload(MultipartForm(form): MultipartForm<ImageUpload>) -> HttpResponse {
    if !exists("userdata").unwrap_or(false) && create_dir_all("userdata").is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to create a directory for user data".into()),
            data: None::<ImageData>,
        });
    }

    let id = generate_random_sequence(6, random::CHARACTER_POOL);

    let old_path = form.file.file.path();
    let new_path = format!("userdata/{}.png", id);

    if copy(old_path, new_path).is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to copy the file".into()),
            data: None::<ImageData>,
        });
    }

    let image_data = ImageData { id };

    HttpResponse::Created().json(Response {
        status_code: 201,
        message: None,
        data: Some(image_data),
    })
}
