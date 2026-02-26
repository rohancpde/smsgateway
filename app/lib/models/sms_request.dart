class SmsRequest {
  final int id;
  final String phoneNumber;
  final String message;
  final int priority;
  final String? scheduledAt;
  final int simSlot;
  final int retryCount;

  SmsRequest({
    required this.id,
    required this.phoneNumber,
    required this.message,
    required this.priority,
    this.scheduledAt,
    this.simSlot = 0,
    this.retryCount = 0,
  });

  factory SmsRequest.fromJson(Map<String, dynamic> json) {
    return SmsRequest(
      id: json['id'] is int ? json['id'] as int : int.parse(json['id'].toString()),
      phoneNumber: json['phone_number'] as String,
      message: json['message'] as String,
      priority: json['priority'] is int
          ? json['priority'] as int
          : int.tryParse(json['priority']?.toString() ?? '') ?? 5,
      scheduledAt: json['scheduled_at'] as String?,
      simSlot: json['sim_slot'] is int
          ? json['sim_slot'] as int
          : int.tryParse(json['sim_slot']?.toString() ?? '') ?? 0,
      retryCount: json['retry_count'] is int
          ? json['retry_count'] as int
          : int.tryParse(json['retry_count']?.toString() ?? '') ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'phone_number': phoneNumber,
      'message': message,
      'priority': priority,
      'scheduled_at': scheduledAt,
      'sim_slot': simSlot,
      'retry_count': retryCount,
    };
  }

  bool get isRetry => retryCount > 0;
}
