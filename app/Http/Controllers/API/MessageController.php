<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\Facades\Tool;
use App\Models\Professor;

class MessageController extends Controller
{
    public function sendMessage(Request $request, $id)
    {
        $request->validate([
            'message'         => 'required|string|max:1000',
        ]);

        $conversation = Conversation::where('id', $id)
            ->where('professor_id', Auth::id())
            ->with('messages')
            ->first();

        if (!$conversation) {
            return response()->json([
                'response_code' => 404,
                'status'        => 'error',
                'message'       => 'Conversation not found',
            ]);
        }

        // Take last 20 messages for context
        $messagesSorted = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $structuredMessages = $messagesSorted->map(function ($msg) {
            return $msg->sender === 'professor'
                ? new UserMessage($msg->message)
                : new AssistantMessage($msg->message);
        })->toArray();

        // Example tool: Get professor info
        $professorInfoTool = Tool::as('professorInfo')
            ->for('Get authenticated professor information')
            ->using(function (): string {
                $prof = Professor::find(Auth::id());

                if (!$prof) {
                    return json_encode(['error' => 'No authenticated professor']);
                }

                return json_encode([
                    'id'          => $prof->id,
                    'name'        => $prof->name,
                    'email'       => $prof->email,
                    'institute_id'=> $prof->institute_id,
                ]);
        });


        $weatherTool = Tool::as('weather') //mock tools from documentation
            ->for('Get current weather conditions')
            ->withStringParameter('city', 'The city to get weather for')
            ->using(function (string $city): string {
                return "The weather in {$city} is sunny and 72°F.";
        });

        $instituteInfoTool = Tool::as('instituteInfo')
        ->for('Get the institute information for the authenticated professor')
        ->using(function (): string {
            $prof = \App\Models\Professor::find(Auth::id());

            if (!$prof || !$prof->institute) {
                return json_encode(['error' => 'No institute found']);
            }

            $inst = $prof->institute;
            return json_encode([
                'id'          => $inst->id,
                'name'        => $inst->name,
                'full_name'   => $inst->full_name,
                'description' => $inst->description,
            ]);
        });

        $classroomsTool = Tool::as('classrooms')
        ->for('Get all active classrooms taught by the authenticated professor, including AI-related fields')
        ->using(function (): string {
            $prof = Professor::find(Auth::id());

            if (!$prof) {
                return json_encode(['error' => 'No authenticated professor']);
            }

            $classrooms = $prof->classrooms()
                ->where('status', 'active')
                ->select(
                    'id',
                    'name',
                    'subject',
                    'description',
                    'code',
                    'status',
                    'sentiment_analysis',
                    'ai_analysis',
                    'ai_recommendation'
                )
                ->get();

            return $classrooms->toJson();
        });



        try {
            $responseAI = Prism::text()
                ->using(Provider::Gemini, 'gemini-2.5-flash-lite') //gemini-2.0-flash not working
                ->withSystemPrompt('You are a friendly assistant for professors. Always address the professor by name and be helpful.')
                ->withMessages([
                    ...$structuredMessages,
                    new UserMessage($request->message),
                ])
                ->withTools([$professorInfoTool, $weatherTool, $instituteInfoTool, $classroomsTool])
                ->withMaxSteps(5)
                ->asText();

            // Save professor message
            Message::create([
                'conversation_id' => $id,
                'message'         => $request->message,
                'sender'          => 'professor',
            ]);

            // Save AI reply
            Message::create([
                'conversation_id' => $id,
                'message'         => $responseAI->text,
                'sender'          => 'ai',
            ]);

            return response()->json([
                'status' => 'success',
                'response' => $responseAI->text,
                'structuredMessages' => $structuredMessages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
