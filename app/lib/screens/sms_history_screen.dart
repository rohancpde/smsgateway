import 'package:flutter/material.dart';
import '../services/secure_storage_service.dart';
import '../services/api_service.dart';

class SmsHistoryScreen extends StatefulWidget {
  const SmsHistoryScreen({super.key});

  @override
  State<SmsHistoryScreen> createState() => _SmsHistoryScreenState();
}

class _SmsHistoryScreenState extends State<SmsHistoryScreen> {
  List<Map<String, dynamic>> _messages = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  Future<void> _loadHistory() async {
    setState(() => _isLoading = true);

    final apiUrl = await SecureStorageService.getApiUrl();
    final apiKey = await SecureStorageService.getApiKey();

    if (apiUrl == null || apiKey == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    final api = ApiService(apiUrl, apiKey: apiKey);
    final result = await api.getSmsHistory();

    if (mounted) {
      setState(() {
        _isLoading = false;
        if (result['success'] == true) {
          _messages = List<Map<String, dynamic>>.from(result['messages'] ?? []);
        }
      });
    }
  }

  Color _statusColor(String status) {
    return switch (status) {
      'sent' => Colors.green,
      'delivered' => Colors.green.shade700,
      'failed' => Colors.red,
      'pending' => Colors.orange,
      'processing' => Colors.blue,
      _ => Colors.grey,
    };
  }

  String _statusText(String status) {
    return switch (status) {
      'sent' => 'Gonderildi',
      'delivered' => 'Teslim',
      'failed' => 'Basarisiz',
      'pending' => 'Bekliyor',
      'processing' => 'Isleniyor',
      'cancelled' => 'Iptal',
      _ => status,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.blue.shade50,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: const Text(
          'SMS Gecmisi',
          style: TextStyle(color: Colors.blue, fontWeight: FontWeight.bold),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.blue),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.blue),
            onPressed: _loadHistory,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _messages.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.inbox, size: 64, color: Colors.grey),
                      SizedBox(height: 16),
                      Text(
                        'SMS gecmisi bos',
                        style: TextStyle(fontSize: 18, color: Colors.grey),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadHistory,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _messages.length,
                    itemBuilder: (context, index) {
                      final msg = _messages[index];
                      final status = (msg['status'] ?? 'pending').toString();
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: ListTile(
                          contentPadding: const EdgeInsets.all(12),
                          leading: CircleAvatar(
                            backgroundColor: _statusColor(status).withValues(alpha: 0.1),
                            child: Icon(
                              status == 'sent' || status == 'delivered'
                                  ? Icons.check
                                  : status == 'failed'
                                      ? Icons.close
                                      : Icons.schedule,
                              color: _statusColor(status),
                            ),
                          ),
                          title: Text(
                            msg['phone_number']?.toString() ?? '-',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const SizedBox(height: 4),
                              Text(
                                msg['message']?.toString() ?? '',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 13,
                                  color: Colors.grey.shade600,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: _statusColor(status).withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      _statusText(status),
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold,
                                        color: _statusColor(status),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    'Oncelik: ${msg['priority'] ?? 5}',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: Colors.grey.shade500,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
