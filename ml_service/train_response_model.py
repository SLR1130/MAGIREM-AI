"""
Train a response generation model using DistilGPT-2.
This learns from conversation data instead of hardcoded templates.
"""

import json
import torch
import random
from pathlib import Path
from transformers import (
    GPT2Tokenizer,
    GPT2LMHeadModel,
    Trainer,
    TrainingArguments,
    DataCollatorForLanguageModeling,
)
from datasets import Dataset

class ResponseModelTrainer:
    def __init__(self):
        self.model_name = "distilgpt2"
        self.output_dir = Path("./response_model/")
        self.output_dir.mkdir(exist_ok=True)
        
    def prepare_training_data(self):
        """Convert JSONL training data to text format matching how we generate (with prompt prefix)"""
        
        # Make paths relative to this script's directory (ml_service/)
        script_dir = Path(__file__).parent
        
        # PRIORITIZE LARGE DATASET (500 examples)
        input_file = script_dir / "response_training_data_large.jsonl"
        
        # Only fall back if large doesn't exist
        if not input_file.exists():
            input_file = script_dir / "response_training_data.jsonl"
        
        if not input_file.exists():
            print(f"ERROR: Training data not found")
            print(f"  Looked for: {script_dir / 'response_training_data_large.jsonl'}")
            print(f"  Looked for: {script_dir / 'response_training_data.jsonl'}")
            return False
        
        file_size_kb = input_file.stat().st_size / 1024
        print(f"[INFO] Using: {input_file.name}")
        print(f"[INFO] File size: {file_size_kb:.1f} KB")
        
        training_text_file = self.output_dir / "training_text.txt"
        
        print("[PREP] Converting JSONL to training format...")
        with open(input_file, 'r', encoding='utf-8') as f:
            lines = f.readlines()
        
        print(f"[DEBUG] Total lines read: {len(lines)}")
        
        # Training format matches generation format: "Emotion: [emotion]\nResponse: [response_text]"
        with open(training_text_file, 'w', encoding='utf-8') as f:
            valid_count = 0
            skipped_count = 0
            for line_num, line in enumerate(lines):
                try:
                    item = json.loads(line)
                    emotion = item.get('emotion', 'neutral').lower()
                    response = item.get('response', '').strip()
                    
                    # Only include valid responses
                    if response and len(response) > 10:
                        # Ensure ASCII-only
                        try:
                            response.encode('ascii')
                            # Format: matches our generation prompt
                            training_text = f"Emotion: {emotion}\nResponse: {response}\n"
                            f.write(training_text)
                            valid_count += 1
                        except UnicodeEncodeError:
                            skipped_count += 1
                    else:
                        skipped_count += 1
                except json.JSONDecodeError:
                    skipped_count += 1
        
        print(f"[RESULT] Valid: {valid_count} | Skipped: {skipped_count}")
        return training_text_file
    
    def train(self):
        """Fine-tune DistilGPT-2 on counselor responses"""
        
        # Prepare data
        train_file = self.prepare_training_data()
        if not train_file:
            return False
        
        print("\n[TRAIN] Loading DistilGPT-2...")
        tokenizer = GPT2Tokenizer.from_pretrained(self.model_name)
        model = GPT2LMHeadModel.from_pretrained(self.model_name)
        
        # Add padding token
        tokenizer.pad_token = tokenizer.eos_token
        
        print("[TRAIN] Loading and tokenizing dataset...")
        
        # Read training data
        with open(train_file, 'r', encoding='utf-8') as f:
            texts = f.readlines()
        
        # Tokenize texts
        def tokenize_function(examples):
            return tokenizer(
                examples['text'],
                padding='max_length',
                truncation=True,
                max_length=128,
                return_tensors='pt'
            )
        
        # Create dataset
        dataset = Dataset.from_dict({'text': texts})
        tokenized_dataset = dataset.map(
            tokenize_function,
            batched=True,
            remove_columns=['text']
        )
        
        data_collator = DataCollatorForLanguageModeling(
            tokenizer=tokenizer,
            mlm=False
        )
        
        training_args = TrainingArguments(
            output_dir=str(self.output_dir),
            num_train_epochs=5,
            per_device_train_batch_size=4,
            save_steps=25,
            save_total_limit=3,
            logging_steps=10,
            learning_rate=5e-5,
            warmup_steps=50,
        )
        
        trainer = Trainer(
            model=model,
            args=training_args,
            data_collator=data_collator,
            train_dataset=tokenized_dataset,
        )
        
        print("[TRAIN] Starting training...")
        trainer.train()
        
        # Save final model
        model_path = self.output_dir / "final"
        model_path.mkdir(exist_ok=True)
        
        model.save_pretrained(str(model_path))
        tokenizer.save_pretrained(str(model_path))
        
        print(f"[OK] Model saved to: {model_path}")
        return True

def main():
    print("="*60)
    print("[RESPONSE] Training Counselor Response Generation Model")
    print("="*60)
    
    print("\n[STEP 1] Using pre-generated training data...")
    
    print("\n[STEP 2] Training response model...")
    trainer = ResponseModelTrainer()
    success = trainer.train()
    
    if success:
        print("\n" + "="*60)
        print("[OK] TRAINING COMPLETE")
        print("="*60)
        print("\nNext: Restart the ML API to load new weights")
    else:
        print("\n[ERROR] Training failed")

if __name__ == '__main__':
    main()
