import{_ as r}from"./Button.4631b684.js";import{_ as n}from"./Modal.9639af57.js";import{h as c,c as g,a as d,w as a,p as _,o as p,b as s,t as e,u as x}from"./app.c4e47028.js";import{r as y}from"./ArrowUturnLeftIcon.8d3b347e.js";import"./keyboard.58689cfa.js";import"./disposables.be045d92.js";const h={class:"flex flex-col md:flex-row space-x-2"},f={class:"text-gray-600"},u={class:"mt-6 border-t border-gray-100"},v={class:"divide-y divide-gray-100"},b={class:"px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0"},C=s("dt",{class:"text-sm font-medium leading-6 text-gray-900"}," Short Order ID ",-1),O={class:"mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0"},P={class:"px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0"},j=s("dt",{class:"text-sm font-medium leading-6 text-gray-900"}," Phone Number ",-1),N={class:"mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0"},k={class:"px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0"},w=s("dt",{class:"text-sm font-medium leading-6 text-gray-900"}," Driver Name ",-1),M={class:"mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0"},$={class:"px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0"},B=s("dt",{class:"text-sm font-medium leading-6 text-gray-900"}," Problem ",-1),I={class:"mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0"},S={class:"px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0"},D=s("dt",{class:"text-sm font-medium leading-6 text-gray-900"}," Qty Affected ",-1),A={class:"mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0"},V={class:"px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0"},J=s("dt",{class:"text-sm font-medium leading-6 text-gray-900"}," Remarks ",-1),R={class:"mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0"},Q={class:"sm:col-span-6"},T={class:"flex space-x-1 mt-5 justify-end"},q=s("span",null," Back ",-1),U={__name:"Complaint",props:{model:[Array,Object],permissions:[Array,Object],type:String,showModal:Boolean},emits:["modalClose"],setup(t,{emit:z}){const i=t;return c(()=>{console.log(JSON.parse(JSON.stringify(i.model)))}),(m,o)=>(p(),g(_,{to:"body"},[d(n,{open:t.showModal,onModalClose:o[1]||(o[1]=l=>m.$emit("modalClose"))},{header:a(()=>[s("div",h,[s("span",f,e(t.model.deliveryProductMappingVend.vend.full_name),1)])]),default:a(()=>[s("div",u,[s("dl",v,[s("div",b,[C,s("dd",O,e(t.model.short_order_id),1)]),s("div",P,[j,s("dd",N,e(t.model.driver_phone_number),1)]),s("div",k,[w,s("dd",M,e(t.model.deliveryPlatformOrderComplaint.original_json.IssueDriverName),1)]),s("div",$,[B,s("dd",I,e(t.model.deliveryPlatformOrderComplaint.original_json.IssueProblem),1)]),s("div",S,[D,s("dd",A,e(t.model.deliveryPlatformOrderComplaint.original_json.IssueProductMissing),1)]),s("div",V,[J,s("dd",R,e(t.model.deliveryPlatformOrderComplaint.original_json.IssueRemark),1)])])]),s("div",Q,[s("div",T,[d(r,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[0]||(o[0]=l=>m.$emit("modalClose")),form:"submit"},{default:a(()=>[d(x(y),{class:"w-4 h-4"}),q]),_:1})])])]),_:1},8,["open"])]))}};export{U as default};
