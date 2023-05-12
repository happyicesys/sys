import{h as f,T as v,K as C,j,c as O,a as l,w as d,s as $,o as n,b as s,f as i,m as y,t as x,d as m,u as b,e as B}from"./app.69f34740.js";import{_ as h}from"./Button.5adfb1f9.js";import{_ as p}from"./FormInput.5914a32e.js";import{_ as U}from"./Modal.2750dab9.js";import{_ as w}from"./MultiSelect.abb3fbf2.js";import{r as N}from"./ArrowUturnLeftIcon.c99bd19e.js";import{r as M}from"./CheckCircleIcon.be3634df.js";import"./open-closed.1b2a7114.js";const E={class:"flex flex-col md:flex-row space-x-2"},T={key:0,class:"text-gray-600"},P={key:1},R={key:2,class:"text-gray-600"},D=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},q={class:"col-span-12 sm:col-span-6"},A={class:"col-span-12 sm:col-span-6"},K={class:"col-span-12 sm:col-span-6"},L={class:"col-span-12 sm:col-span-6"},z={key:0,class:"col-span-12 sm:col-span-6"},G=s("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Role ",-1),H={class:"col-span-12 sm:col-span-6"},I=s("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),J={key:0,class:"text-sm text-red-600"},Q={class:"sm:col-span-6"},W={class:"flex space-x-1 mt-5 justify-end"},X=s("span",null," Back ",-1),Y=s("span",null," Save ",-1),de={__name:"Form",props:{user:Object,countries:Object,operators:Object,permissions:[Array,Object],roles:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:g}){const t=u,e=f(v(_())),V=f([]),c=f([]),k=C().props.auth.operatorRole;j(()=>{e.value=t.user?v({..._(),...t.user}):v(_()),V.value=t.operators.data,c.value=t.roles.data,t.permissions.includes("admin-access operators")||(c.value=t.roles.data.filter(function(r){return r.name=="operator"||r.name=="operator_user"}))});function _(){return{name:"",email:"",username:"",password:"",operator_id:"",role_id:""}}function S(){e.value.clearErrors(),t.type==="create"&&e.value.transform(r=>({...r,operator_id:r.operator_id.id,role_id:r.role_id.id})).post("/users/create",{onSuccess:()=>{g("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.transform(r=>({...r,operator_id:r.operator_id.id,role_id:r.role_id.id})).post("/users/"+e.value.id+"/update",{onSuccess:()=>{g("modalClose")},preserveState:!0,replace:!0})}return(r,o)=>(n(),O($,{to:"body"},[l(U,{open:u.showModal,onModalClose:o[7]||(o[7]=a=>r.$emit("modalClose"))},{header:d(()=>[s("div",E,[t.user?(n(),i("span",T," Editing ")):y("",!0),t.user?(n(),i("span",P,x(t.user.name),1)):(n(),i("span",R," Create New User "))])]),default:d(()=>[s("form",{onSubmit:B(S,["prevent"]),id:"submit"},[s("div",F,[s("div",q,[l(p,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:d(()=>[m(" Name ")]),_:1},8,["modelValue","error"])]),s("div",A,[l(p,{modelValue:e.value.email,"onUpdate:modelValue":o[1]||(o[1]=a=>e.value.email=a),error:e.value.errors.email},{default:d(()=>[m(" Email ")]),_:1},8,["modelValue","error"])]),s("div",K,[l(p,{modelValue:e.value.username,"onUpdate:modelValue":o[2]||(o[2]=a=>e.value.username=a),error:e.value.errors.username},{default:d(()=>[m(" Username ")]),_:1},8,["modelValue","error"])]),s("div",L,[l(p,{modelValue:e.value.password,"onUpdate:modelValue":o[3]||(o[3]=a=>e.value.password=a),error:e.value.errors.password,placeholderStr:[u.type=="update"?"Leave blank for same password":""],inputType:"password",autocomplete:"new-password"},{default:d(()=>[m(" Password "+x(u.type=="update"?"(Override)":""),1)]),_:1},8,["modelValue","error","placeholderStr"])]),b(k)?y("",!0):(n(),i("div",z,[G,l(w,{modelValue:e.value.role_id,"onUpdate:modelValue":o[4]||(o[4]=a=>e.value.role_id=a),options:c.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),s("div",H,[I,l(w,{modelValue:e.value.operator_id,"onUpdate:modelValue":o[5]||(o[5]=a=>e.value.operator_id=a),options:V.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"top",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.operator_id?(n(),i("div",J,x(e.value.errors.operator_id),1)):y("",!0)])]),s("div",Q,[s("div",W,[l(h,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[6]||(o[6]=a=>r.$emit("modalClose")),form:"submit"},{default:d(()=>[l(b(N),{class:"w-4 h-4"}),X]),_:1}),l(h,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:d(()=>[l(b(M),{class:"w-4 h-4"}),Y]),_:1})])])],40,D)]),_:1},8,["open"])]))}};export{de as default};
